package variance.saxonsvc;
import com.sun.net.httpserver.HttpServer;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.Headers;
import java.io.*;
import java.net.InetSocketAddress;
import java.nio.file.*;
import java.util.*;
import javax.json.*;

/**
 * Very small HTTP wrapper for Saxon transformation.
 * Listens on port 8080.
 */
public class TransformServer {

    private static final String SAXON_JAR =
        "/opt/saxon/saxon-he-12.3.jar:/opt/saxon/xmlresolver-4.5.0.jar";  // absolute
    private static final String STYLESHEET =
        "/app/tei2xhtml.xsl";                                             // absolute

    public static void main(String[] args) throws Exception {
        HttpServer server = HttpServer.create(new InetSocketAddress(8080), 0);
        server.createContext("/run-xhtml", TransformServer::handleRun);
        server.setExecutor(null);
        System.out.println("Saxon TransformServer listening on :8080");
        server.start();
    }

    private static void handleRun(HttpExchange ex) throws IOException {
        if (!"POST".equalsIgnoreCase(ex.getRequestMethod())) {
            ex.sendResponseHeaders(405, -1);  // Method Not Allowed
            return;
        }

        JsonObject body;
        try (InputStream is = ex.getRequestBody();
             JsonReader reader = Json.createReader(is)) {
            body = reader.readObject();
        } catch (Exception e) {
            respond(ex, 400, "{\"error\":\"Invalid JSON\"}");
            return;
        }

        String input = body.getString("input_xml", "").trim();
        String outputDir = body.getString("output_dir", "").trim();

        if (input.isEmpty() || outputDir.isEmpty()) {
            respond(ex, 400,
                "{\"error\":\"input_xml and output_dir are required\"}");
            return;
        }

        Path inPath = Paths.get("/app").resolve(input).normalize();
        Path outDir = Paths.get("/app").resolve(outputDir).normalize();
        Path outFile = outDir.resolve("out.xhtml");

        if (!Files.exists(inPath)) {
            respond(ex, 404, "{\"error\":\"Input XML not found\"}");
            return;
        }
        Files.createDirectories(outDir);

        List<String> cmd = List.of(
            "java", "-cp", SAXON_JAR,
            "net.sf.saxon.Transform",
            "-s:" + inPath.toString(),
            "-xsl:" + STYLESHEET,
            "-o:" + outFile.toString(),
            "-ext:on"
        );

        ProcessBuilder pb = new ProcessBuilder(cmd)
            .inheritIO();                // stream stdout/err to container logs

        int exit = -1;
        try {
            Process p = pb.start();
            exit = p.waitFor();
        } catch (Exception e) {
            respond(ex, 500,
                "{\"error\":\"Failed to start Saxon\",\"details\":\"" +
                e.getMessage().replace("\"","\\\"") + "\"}");
            return;
        }

        if (exit != 0) {
            respond(ex, 500,
                "{\"error\":\"Saxon exited with code " + exit + "\"}");
            return;
        }

        JsonArrayBuilder files = Json.createArrayBuilder();
        try (DirectoryStream<Path> ds =
                Files.newDirectoryStream(outDir, "*.{xhtml,html,xml}")) {
            for (Path p : ds) files.add(p.getFileName().toString());
        }
        JsonObject ok = Json.createObjectBuilder()
                .add("status", "ok")
                .add("files", files)
                .build();
        respond(ex, 200, ok.toString());
    }

    private static void respond(HttpExchange ex, int code, String json)
            throws IOException {
        Headers h = ex.getResponseHeaders();
        h.set("Content-Type", "application/json");
        byte[] data = json.getBytes();
        ex.sendResponseHeaders(code, data.length);
        try (OutputStream os = ex.getResponseBody()) { os.write(data); }
    }
}
