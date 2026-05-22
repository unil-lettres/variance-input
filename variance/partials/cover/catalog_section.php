<?php
$catalogSectionTitle = (string) ($catalogSectionTitle ?? 'Catalogue');
$catalogWorkIds = array_values(array_map('intval', (array) ($catalogWorkIds ?? [])));
$catalogGroup = ($catalogGroup ?? 'main') === 'allographic' ? 'allographic' : 'main';
$catalogPageParam = trim((string) ($catalogPageParam ?? 'page'));
$catalogPaginate = (bool) ($catalogPaginate ?? true);
$catalogHideWhenEmpty = (bool) ($catalogHideWhenEmpty ?? false);
$catalogPerPage = max(1, (int) ($catalogPerPage ?? 40));
$catalogEmptyMessage = (string) ($catalogEmptyMessage ?? 'Aucune comparaison disponible pour le moment.');
$catalogComparisonQuery = (string) ($catalogComparisonQuery ?? '');
$catalogComparisonFilter = $catalogComparisonFilter ?? null;
$catalogComparisonUrlBuilder = $catalogComparisonUrlBuilder ?? null;
$catalogImagesBaseUrl = '/uploads_images';
$catalogPdfRoot = defined('UPLOAD_ROOT') ? UPLOAD_ROOT . '/pdf' : ROOT . '/uploads/pdf';

if (!$cnx instanceof PDO || $catalogComparisonQuery === '' || !is_callable($catalogComparisonFilter) || !is_callable($catalogComparisonUrlBuilder)) {
    return;
}

$filters = [];
if (empty($catalogWorkIds)) {
    $filters[] = '1=0';
} else {
    $filters[] = 'w.id IN (' . implode(',', $catalogWorkIds) . ')';
}
$filters[] = 'COALESCE(w.catalog_group, \'main\') = :catalog_group';
$whereSql = 'WHERE ' . implode(' AND ', $filters);

$baseQuery = 'SELECT %s a.id as a_id, a.name as a_name, a.folder as a_folder, w.id as w_id, w.image_url as w_image, w.pdf_url as w_pdf, w.is_legacy as w_is_legacy, w.title as w_title, w.folder as w_folder, w.desc as w_desc
FROM `authors` a
INNER JOIN works w ON w.author_id = a.id
' . $whereSql . '
ORDER BY a.`order` ASC, w_id ASC';

$page = 1;
$offset = 0;
$total = 0;
$nbPages = 1;

if ($catalogPaginate) {
    $page = isset($_GET[$catalogPageParam]) ? max(1, (int) $_GET[$catalogPageParam]) : 1;
    $offset = ($page - 1) * $catalogPerPage;
    $query = sprintf($baseQuery, 'SQL_CALC_FOUND_ROWS ') . ' LIMIT :limit OFFSET :offset';
    $queryStatement = $cnx->prepare($query);
    $queryStatement->bindValue(':catalog_group', $catalogGroup, PDO::PARAM_STR);
    $queryStatement->bindValue(':limit', $catalogPerPage, PDO::PARAM_INT);
    $queryStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $queryStatement->execute();
    $elements = $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    $total = (int) $cnx->query('SELECT found_rows()')->fetchColumn();
    $nbPages = max(1, (int) ceil($total / $catalogPerPage));
} else {
    $query = sprintf($baseQuery, '');
    $queryStatement = $cnx->prepare($query);
    $queryStatement->bindValue(':catalog_group', $catalogGroup, PDO::PARAM_STR);
    $queryStatement->execute();
    $elements = $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    $total = count($elements);
}

if ($catalogHideWhenEmpty && $total === 0) {
    return;
}

$previous = ['a_id' => 0, 'w_id' => 0];
$hasResults = !empty($elements);
?>
<div class="row catalogue__title">
    <h2 class="col-sm-9" style="padding-left:0">
        <?php echo htmlspecialchars($catalogSectionTitle, ENT_QUOTES, 'UTF-8'); ?>
    </h2>
    <?php if ($catalogPaginate && $nbPages > 1): ?>
        <div class="catalogue__pagination pull-right">
            <span class="smaller-1">pages</span>
            <?php for ($i = 1; $i <= $nbPages; $i++): ?>
                <?php
                $params = $_GET;
                $params[$catalogPageParam] = $i;
                $href = '?' . http_build_query($params);
                ?>
                <a class="item <?php echo (($page === $i) ? 'active' : ''); ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($elements as $element): ?>
    <div class="catalogue__item row<?php echo (($element['a_id'] === $previous['a_id']) ? ' author_' . $element['a_id'] . '" style="display:none"' : '"'); ?>>
        <?php if ($element['a_id'] !== $previous['a_id']): ?>
            <?php
            $authorName = $element['a_name'];
            $authorFirstNameArr = explode(' ', $authorName, 2);
            $autorFirstName = array_shift($authorFirstNameArr);
            $autorLastName = implode($authorFirstNameArr);
            ?>
            <h3 class="catalogue__author">
                <a href="javascript:void(0);" onclick="$('.author_<?php echo $element['a_id']; ?>').slideToggle('slow');"><span><?php echo htmlspecialchars($autorFirstName, ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars($autorLastName, ENT_QUOTES, 'UTF-8'); ?></a>
            </h3>
            <div class="author_<?php echo $element['a_id']; ?>" style="display:none">
        <?php endif; ?>

        <?php if ($element['w_id'] !== $previous['w_id']): ?>
            <h4 style="padding-left:15px">
                <a href="javascript:void(0);" onclick="$('.work_<?php echo $element['w_id']; ?>').slideToggle('slow');"><?php echo htmlspecialchars($element['w_title'], ENT_QUOTES, 'UTF-8'); ?></a>
            </h4>
            <div class="work_<?php echo $element['w_id']; ?>" style="display:none">
                <div class="img col-sm-4">
                    <img class="img-responsive"
                         src="<?= $catalogImagesBaseUrl ?>/<?= htmlspecialchars($element['w_image'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($element['w_title'], ENT_QUOTES, 'UTF-8') ?>"
                         onerror="this.onerror=null;this.src='<?= DIR_REL ?>/img/cover_site/main_visuel.png';" />
                </div>
            <div class="col-sm-8">
                <div>
                    <?php echo '<p>' . $element['w_desc'] . '</p>'; ?>
                </div>
                <?php
                $comparisonStatement = $cnx->prepare($catalogComparisonQuery);
                $comparisonStatement->bindValue(':id', $element['w_id'], PDO::PARAM_INT);
                $comparisonStatement->execute();
                $comparisons = array_values(array_filter(
                    $comparisonStatement->fetchAll(PDO::FETCH_ASSOC),
                    static function ($comparison) use ($catalogComparisonFilter, $element) {
                        return $catalogComparisonFilter($comparison, $element);
                    }
                ));
                $isPlural = count($comparisons) > 1;
                ?>

                <?php if (!empty($comparisons)): ?>
                    <p><strong>Comparaison<?php echo ($isPlural ? 's' : ''); ?></strong></p>
                    <style>
                        .wrapper_flex { display: flex; padding: 5px 0 5px; border-bottom: #f1f1f1 1px solid; }
                        .wrapper_flex > div { flex: 1; }
                        #General-Wrapper .content-cover ul.catalogue-versions { padding-bottom: 0 !important; }
                        .wrapper_flex:hover { background-color: #EEEEEE; }
                        @keyframes changewidth { from { margin-left: 0; } to { margin-left: 30px; } }
                        .wrapper_flex:hover .arrow-versions { animation-duration: 1s; animation-name: changewidth; animation-iteration-count: infinite; animation-direction: alternate; }
                        .dia_btn { margin-top: 1em }
                    </style>
                    <?php $displayIndex = 1; ?>
                    <?php foreach ($comparisons as $version): ?>
                        <?php
                        $prefix = isset($version['c_prefix_label']) ? trim($version['c_prefix_label']) : '';
                        if ($prefix !== '' && stripos($prefix, 'auto') === 0) {
                            $prefix = '';
                        }
                        if ($prefix !== '' && substr($prefix, -1) !== ' ') {
                            $prefix .= ' ';
                        }
                        $sourceLabel = trim($prefix . $version['s_name']);
                        $versionHref = $catalogComparisonUrlBuilder($version, $element);
                        ?>
                        <a class="wrapper_menu_a" title="cliquez pour comparer" href="<?php echo htmlspecialchars($versionHref, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="wrapper_flex">
                                <div style="white-space: nowrap;"><?php echo $displayIndex . '. ' . htmlspecialchars($sourceLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div style="text-align: center"><span class="arrow-versions">&rarr;</span></div>
                                <div style="text-align: right; white-space: nowrap;"><?php echo htmlspecialchars($version['t_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </a>
                        <?php $displayIndex++; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <br style="clear:both" />
                <?php
                $pdfFile = trim((string) ($element['w_pdf'] ?? ''));
                if ($pdfFile === '' && (bool) ($element['w_is_legacy'] ?? false)) {
                    $pdfFile = (int) $element['w_id'] . '.pdf';
                }
                $pdfFile = basename($pdfFile);
                $pdfPath = $pdfFile !== '' ? $catalogPdfRoot . '/' . $pdfFile : '';
                if ($pdfFile !== '' && is_file($pdfPath)):
                ?>
                    <div class="dia_btn align-right primary small">
                        <a href="/uploads/pdf/<?php echo htmlspecialchars($pdfFile, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Notice</a>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        <?php endif; ?>

        <?php if ($element['a_id'] !== $previous['a_id']): ?>
            </div>
        <?php endif; ?>
    </div>
    <?php $previous = $element; ?>
<?php endforeach; ?>

<?php if (!$hasResults): ?>
    <div class="text-muted" style="padding: 10px 0;"><?php echo htmlspecialchars($catalogEmptyMessage, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if ($catalogPaginate && $nbPages > 1): ?>
    <div class="catalogue__pagination pull-right">
        <span class="smaller-1">pages</span>
        <?php for ($i = 1; $i <= $nbPages; $i++): ?>
            <?php
            $params = $_GET;
            $params[$catalogPageParam] = $i;
            $href = '?' . http_build_query($params);
            ?>
            <a class="item <?php echo (($page === $i) ? 'active' : ''); ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
