<?php

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class LegacyChapterRenderingTest extends TestCase
{
    public static function readerPaths(): array
    {
        return [['variance/backoff/includes/chapter_functions.php'], ['variance/dev/backoff/includes/chapter_functions.php']];
    }

    #[DataProvider('readerPaths')]
    public function test_null_and_zero_roots_render_once_with_children_and_folder_isolation(string $path): void
    {
        global $cnx;
        $cnx = new PDO('sqlite::memory:');
        $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cnx->exec('CREATE TABLE chapters (id INTEGER, folder TEXT, level TEXT, label_source TEXT, label_target TEXT, chapter_parent INTEGER, start_line_source TEXT, start_line_target TEXT, id_tome_source INTEGER, id_tome_target INTEGER)');
        $insert = $cnx->prepare('INSERT INTO chapters VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ([[1, null, 'Legacy root'], [2, 0, 'Modern root'], [3, 1, 'Legacy child'], [4, 2, 'Modern child'], [5, 99, 'Orphan']] as [$id, $parent, $label]) {
            $insert->execute([$id, 'fixture', '1', $label, $label, $parent, '1a', '2a', 0, 0]);
        }
        $insert->execute([6, 'other', '1', 'Other work', 'Other work', null, '1a', '2a', 0, 0]);
        require dirname(__DIR__, 3) . '/' . $path;
        foreach (['source', 'target'] as $side) {
            ob_start();
            try { displayChapters('fixture', $side); $html = ob_get_contents(); }
            finally { ob_end_clean(); }
            $this->assertSame(4, substr_count($html, '<li>'));
            foreach (['Legacy root', 'Modern root', 'Legacy child', 'Modern child'] as $label) {
                $this->assertSame(1, substr_count($html, '>' . $label . '</a>'));
            }
            $this->assertStringNotContainsString('Orphan', $html);
            $this->assertStringNotContainsString('Other work', $html);
            $this->assertStringContainsString("goToPageNumber('1a','2a','0','0')", $html);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            foreach (['Legacy', 'Modern'] as $kind) {
                $this->assertSame(1, $xpath->query('//li[a[text()="' . $kind . ' root"]]/ul/li/a[text()="' . $kind . ' child"]')->length);
            }
        }
    }
}
