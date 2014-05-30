<?php
namespace CB;

/*
selecting node properties from the tree
comparing last preview access time with node update time. Generate preview if needed and store it in cache
checking if preview is available and return it
 */
if (empty($_GET['f'])) {
    exit(0);
}

//init
require_once 'init.php';

$coreUrl = Config::get('core_url');
$filesPreviewDir = Config::get('files_preview_dir');

//detect id
$f = $_GET['f'];
$f = explode('.', $f);
$a = array_shift($f);
@list($id, $version_id) = explode('_', $a);
$ext = array_pop($f);

// check login
if (!User::isLoged()) {
    header('Location: ' . $coreUrl . 'login.php?view=' . $id);
    exit(0);
}

if (empty($ext)) {
    //check access with security model
    if (!Security::canRead($id)) {
        echo L\get('Access_denied');
        exit(0);
    }

} else { //this should be an affiliate file generated by the preview process
    $f = realpath($filesPreviewDir . $_GET['f']);
    if (file_exists($f)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        header('Content-type: '.finfo_file($finfo, $f));
        echo file_get_contents($f);
    }
    exit(0);
}

if (!is_numeric($id)) {
    exit(0);
}

$toolbarItems = array(
    '<a href="' . $coreUrl . '?locate=' . $id . '">' . L\get('OpenInCasebox') .'</a>'
);

$obj = Objects::getCachedObject($id);
$objData = $obj->getData();
$objType = $obj->getType();

// if external window then print the toolbar
if (empty($_GET['i'])) {
    echo '<html><head><link rel="stylesheet" type="text/css" href="/css/tasks.css" /></head><body>';
    if ($objType == 'file') {
        $toolbarItems[] = '<a href="' . $coreUrl . 'download.php?id=' . $id . '">' . L\get('Download') .'</a>';
    }

    echo '<table border="0" cellspacing="12" cellpading="12"><tr><td>'.implode('</td><td>', $toolbarItems).'</td></tr></table>';
}

$preview = array();

switch ($obj->getType()) {

    case 'task':
        $o = new Tasks();
        echo $o->getTaskInfoForEmail($id);
        // echo $o->getPreview($id);
        break;

    case 'file':
        $sql = 'SELECT p.filename
            FROM files f
            JOIN file_previews p ON f.content_id = p.id
            WHERE f.id = $1';

        if (!empty($version_id)) {
            $sql = 'SELECT p.filename
                FROM files_versions f
                JOIN file_previews p ON f.content_id = p.id
                WHERE f.file_id = $1
                    AND f.id = $2';
        }

        $res = DB\dbQuery($sql, array($id, $version_id)) or die(DB\dbQueryError());

        if ($r = $res->fetch_assoc()) {
            if (!empty($r['filename']) && file_exists($filesPreviewDir . $r['filename'])) {
                $preview = $r;
            }
        }
        $res->close();

        if (empty($preview)) {
            $preview = Files::generatePreview($id, $version_id);
        }

        if (!empty($preview['processing'])) {
            echo '&#160';

        } else {
            $top = '';
            $tmp = Tasks::getActiveTasksBlockForPreview($id);
            if (!empty($tmp)) {
                $top = '<div class="obj-preview-h pt10">'.L\get('ActiveTasks').'</div>'.$tmp;
            }
            if (!empty($top)) {
                echo //'<div class="p10">'.
                $top.
                // '</div>'.
                '<hr />';
            }

            if (!empty($preview['filename'])) {
                $fn = $filesPreviewDir . $preview['filename'];
                if (file_exists($fn)) {
                    echo file_get_contents($fn);
                    $res = DB\dbQuery(
                        'UPDATE file_previews
                        SET ladate = CURRENT_TIMESTAMP
                        WHERE id = $1',
                        $id
                    ) or die(DB\dbQueryError());
                }
            } elseif (!empty($preview['html'])) {
                echo $preview['html'];
            }
            // $dbNode = new TreeNode\Dbnode();
            // echo '<!-- NodeName:'.$dbNode->getName($id).' -->';
        }
        break;

    default:
        $o = new Objects();
        echo $o->getPreview($id);
        break;
}
