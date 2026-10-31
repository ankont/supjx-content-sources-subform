<?php
$root = dirname(__DIR__) . '/package';
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function manifest($path) {
    $doc = new DOMDocument();
    check($doc->load($path), 'Invalid XML: ' . $path);
    return new DOMXPath($doc);
}
function value($xml, $path) { return trim((string) $xml->evaluate('string(' . $path . ')')); }
function language($path) {
    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    check(is_array($values), 'Invalid language file: ' . $path);
    return $values;
}
$package = manifest($root . '/pkg_sources.xml');
$version = value($package, '/extension/version');
$date = value($package, '/extension/creationDate');
check((bool) preg_match('/^\d+\.\d+\.\d+$/D', $version), 'Invalid package version');
check((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date), 'Missing release date');
check(value($package, '/extension/name') === 'SuperSoftJx - Sources', 'Package display name');
check(value($package, '/extension/packagename') === 'sources', 'Package identity changed');
$extensions = [
    ['content', 'sources', 'sources.xml', 'PLG_CONTENT_SOURCES', 'en-GB.plg_content_sources', 'el-GR.plg_content_sources', 'Content', 'Περιεχόμενο', 'SuperSoft\\Plugin\\Content\\Sources'],
    ['editors-xtd', 'sourcesbutton', 'sourcesbutton.xml', 'PLG_EDITORSXTD_SOURCESBUTTON', 'en-GB.plg_editors-xtd_sourcesbutton', 'el-GR.plg_editors-xtd_sourcesbutton', 'Button', 'Κουμπί', 'SuperSoft\\Plugin\\EditorsXtd\\SourcesButton'],
    ['fields', 'sources', 'sources.xml', 'PLG_FIELDS_SOURCES', 'plg_fields_sources', 'plg_fields_sources', 'Fields', 'Πεδία', 'SuperSoft\\Plugin\\Fields\\Sources'],
];
$manifests = [$package];
$email = manifest($root . '/plugins/newsletter/sourcesemail/sourcesemail.xml');
check(value($email, '/extension/version') === '1.1.1', 'Newsletter provider has independent version');
check(value($email, '/extension/@group') === 'newsletter' && value($email, '/extension/namespace') === 'SuperSoft\\Plugin\\Newsletter\\SourcesEmail', 'Newsletter provider identity');
$emailDate = value($email, '/extension/creationDate');
check((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/D', $emailDate) && $emailDate <= $date, 'Independent newsletter provider release date');
check($package->query('/extension/files/file[@id="sourcesemail" and @group="newsletter"]')->length === 1, 'Provider shipped in package');
foreach ($extensions as [$group, $element, $file, $key, $enFile, $elFile, $enGroup, $elGroup, $namespace]) {
    $directory = $root . '/plugins/' . $group . '/' . $element;
    $xml = manifest($directory . '/' . $file); $manifests[] = $xml;
    check(value($xml, '/extension/@group') === $group, 'Plugin group changed');
    check(value($xml, '/extension/name') === $key, 'Language key changed');
    check(value($xml, '/extension/files/folder[@plugin]/@plugin') === $element, 'Plugin element changed');
    check(value($xml, '/extension/namespace') === $namespace, 'Code namespace changed');
    check(value($xml, '/extension/namespace/@path') === 'src', 'Namespace path changed');
    foreach ([['en-GB', $enFile, $enGroup], ['el-GR', $elFile, $elGroup]] as [$tag, $base, $label]) {
        foreach (['.ini', '.sys.ini'] as $suffix) {
            $filename = $tag . '/' . $base . $suffix;
            check($xml->query('/extension/languages/language[text()="' . $filename . '"]')->length === 1, 'Language filename changed');
            $ini = language($directory . '/language/' . $filename);
            if ($tag === 'el-GR') foreach ($ini as $translated) {
                check(!preg_match('/\bblocks?\b/i', $translated), 'English block terminology remains in Greek: ' . $filename);
            }
            check(($ini[$key] ?? '') === $label . ' - SuperSoftJx - Sources', 'Localized plugin name: ' . $filename);
            if ($group === 'fields') check($ini['PLG_FIELDS_SOURCES_LABEL'] === 'SupJx Sources (sources)', 'Field type label changed');
        }
    }
}
foreach ($manifests as $xml) {
    check(value($xml, '/extension/version') === $version, 'Plugin/package version mismatch');
    check(value($xml, '/extension/author') === 'SuperSoft — Kontarinis Andreas', 'Incomplete author');
    check(value($xml, '/extension/creationDate') === $date, 'Missing/inconsistent release date');
}
foreach (['en-GB', 'el-GR'] as $tag) foreach (['.ini', '.sys.ini'] as $suffix) {
    $ini = language($root . '/language/' . $tag . '/pkg_sources' . $suffix);
    check($ini['PKG_SOURCES'] === 'SuperSoftJx - Sources', 'Localized package name');
}
echo "Extension metadata and technical identities passed\n";
