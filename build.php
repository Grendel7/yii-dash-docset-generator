<?php

$inputDirectory = __DIR__ . '/input/';
$outputDirectory = __DIR__ . '/Yii1.docset/Contents/Resources/Documents/';
$database = __DIR__ . '/Yii1.docset/Contents/Resources/docSet.dsidx';

// prepare the index db
$db = new PDO('sqlite:'.$database);

// create the output directory
if (!is_dir($outputDirectory)) {
    mkdir($outputDirectory, '755', true);
}

// read all the HTML files from the input directory
$input = array_filter(scandir($inputDirectory), function($name) {
    return strpos($name, '.html');
});

// parse all the files in the input dir
foreach ($input as $file) {
    $content = file_get_contents($inputDirectory.$file);

    if ($file != 'index.html') {
        $doc = new DOMDocument();
        @$doc->loadHTML($content);
        $xml = simplexml_import_dom($doc);

        $class = (string) $xml->body->xpath('div[@id="apiPage"]/div[@id="content"]/h1')[0];

        insert($db, $class, 'Class', $class.'.html');

        echo $class . PHP_EOL;

        $propertyRows = $xml->body->xpath('div[@id="apiPage"]/div[@id="content"]/div[@class="summary docProperty"]/table/tr');

        foreach($propertyRows as $row) {
            if ($row->th || !isset($row->td->a)) {
                continue;
            }

            insert($db, $class.'::$'.$row->td->a, 'Property', $row->td->a->attributes()['href']);
        }

        $methodRows = $xml->body->xpath('div[@id="apiPage"]/div[@id="content"]/div[@class="summary docMethod"]/table/tr');

        foreach($methodRows as $row) {
            if ($row->th || $row->attributes()['class'] == 'inherited') {
                continue;
            }

            insert($db, $class.'::'.$row->td->a, 'Method', $row->td->a->attributes()['href']);
        }
    }

    file_put_contents($outputDirectory.$file, $content);
}

function insert($db, $name, $type, $path)
{
    $query = "INSERT OR IGNORE INTO searchIndex(name, type, path) VALUES ('{$name}', '{$type}', '{$path}')";
    if (!$db->exec($query)) {
        echo "Unable to execute: ".$query.PHP_EOL;
        var_dump($db->errorInfo());
        die();
    }
}