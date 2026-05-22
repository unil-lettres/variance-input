<?php require_once '../php/settings.inc.php';

ini_set('display_errors', -1);
error_reporting(E_ALL);
$error = '';
if (count($_POST) > 0) {
    $folder = isset($_POST['folder']) && trim($_POST['folder'])!='' ? htmlspecialchars_decode($_POST['folder']) : null;
    if ($folder === null) {
      $error = 'La version est introuvable !';
    }

    if ($error === '') {
        $isSuccess = false;
        try {
            require('includes/XLSXReader-master/XLSXReader.php');
            require('includes/chapter_functions.php');
            $xlsx = new XLSXReader($_FILES['chapters']['tmp_name']);
            $sheetNames = $xlsx->getSheetNames();
            $sheet = $xlsx->getSheet(1);
            $data = $sheet->getData();
            $queryStatement = $cnx->prepare("delete from chapters where folder='" . htmlspecialchars($folder) . "'");
            $queryStatement->execute();
            $isSuccess = chapter_analyzeLevels($data, $folder);
        } catch (\Exception $exception) {
            $result = false;
        }

        if ($isSuccess) {
            header('Status: 302 Found', true, 302);
            header('Location: chapitrage.php?result=ok', true, 302);
            exit;
        }

        $error = 'Le fichier est incorrect : il doit etre au format XLSX et contenir des lignes';
    }
}

?>

<?php include_once('../partials/cover/header.php') ?>
<div id="General-Wrapper">

  <header class="header-cover">
    <nav class="container">
      <a class="pull-left col-sm-3" href="<?php echo DIR_REL ?>/"><img class="logo"
                                                                       src="<?php echo DIR_REL ?>/img/full_logo_white.svg"
                                                                       alt="Logo Variance"></a>
      BACK-OFFICE - CHAPITRAGE
    </nav>
  </header>

  <main>
    <div class="content-cover container tutoriel">
      <div class="col-lg-10 col-lg-offset-1 col-md-12">
          <?php
          if ($error != '') {
              echo '<div class="row">';

              echo '<div class="alert alert-warning">
                <strong>Erreur!</strong> '.$error.'
              </div>';

              echo '</div>';
          }
          ?>

          <?php
          if (isset($_GET['result'])) {
              echo '<div class="row">';
              echo '<div class="col-lg-12">';

              echo '<div class="alert alert-success">
                Chapitres importés avec succès
              </div>';

              echo '</div>';
              echo '</div>';
          }
          ?>
        <div class="row">
          <div class="col-lg-12">
          <p>
            <strong>Etape 1 - Choissisez l'oeuvre à chapitrer :</strong>
            <br /><br />
          </p>

          <form method="post" enctype="multipart/form-data">
            <select name="folder" required>
              <option value="">Choix</option>
                <?php

                $queryStatement = $cnx->prepare('select a.name as author_name, w.id, w.title as title, v.id, v.name as version_name, c.source_id, c.folder
from works w
left join authors a on a.id=w.author_id
left join versions v on v.work_id=w.id
left join comparisons c on c.source_id=v.id
where c.source_id is not null');
                $queryStatement->execute();

                while ($element = $queryStatement->fetch(PDO::FETCH_ASSOC)):

                    ?>

                  <option value="<?php echo $element['folder']; ?>"><?php echo $element['author_name']; ?>
                    - <?php echo $element['title']; ?> - <?php echo $element['version_name']; ?></option>
                <?php endwhile; ?>
            </select>

            <p>
              <br/><br/>
              <strong>Etape 2 - Uploader le fichier de chapitrage :</strong>
            </p>

            <p>
              <input type="file" name="chapters" required/>
            </p>

            <p>
              <br/><br/>
              <strong>Etape 3 - Valider</strong>
              <br />
              <span style="color:#bf0000; font-weight:bold">Tous les anciens chapitres de cette version seront supprimés !</span>
            </p>

            <p>
              <input type="submit" value="Valider"/>
            </p>

          </form>
          </div>

        </div>

      </div>
    </div>
  </main>

    <?php include_once('../partials/cover/footer_content.php') ?>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="<?php echo DIR_REL ?>/dist/js/main.min.js"></script>

</body>
</html>