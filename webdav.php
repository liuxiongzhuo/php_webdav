<?php
$username = 'admin';
$password = '123456';
class item
{
    var $path;
    var $fileType; //0 空的 1 目录 2 文件
    var $rootDir;
    var $xml;
    var $scriptName;
    var $name;
    var $lastmodified;
    var $realPath;
    var $contentSize;
    var $contenttype;
    function __construct($path = '', $rootDir = 'public')
    {
        // 规范 $path
        if ($path == '/' || $path == '') {
            $this->path = '';
        } else {
            $this->path = $path;
            if ($this->path[strlen($this->path) - 1] == '/') {
                $this->path = substr($this->path, 0, strlen($this->path) - 1);
            }
            if ($this->path[0] == '/') {
                $this->path = substr($this->path, 1, strlen($this->path) - 1);
            }
        }
        //$rootDir
        $this->rootDir = $rootDir;
        // 得到 $fileType
        if ($path == '') {
            $this->fileType = 1;
        } else if (is_dir($this->rootDir . '/' . $this->path)) {
            $this->fileType = 1;
        } else if (is_file($this->rootDir . '/' . $this->path)) {
            $this->fileType = 2;
        } else {
            $this->fileType = 0;
        }
        //$scriptName
        $this->scriptName = $_SERVER['SCRIPT_NAME'];
        //$name
        if ($this->path == '') {
            $this->name = '/';
        } else {
            $this->name = substr($this->path, strrpos($this->path, '/'));
        }
        //realPath
        if ($this->path) {
            $this->realPath = $this->rootDir . '/' . $this->path;
        } else {
            $this->realPath = $this->rootDir;
        }
        //$lastmodified
        if ($this->fileType != 0) {
            $this->lastmodified = gmdate('D, d M Y H:i:s T', filemtime($this->realPath));
        }
        //$contentSize
        if ($this->fileType == 2) {
            $this->contentSize = filesize($this->realPath);
        }
        //$contenttype
        if ($this->fileType == 2) {
            $this->contenttype = mime_content_type($this->realPath);
        }
        //$xml
        if ($this->fileType == 1) {
            $this->xml = "<D:response><D:href>{$this->scriptName}/{$this->path}</D:href><D:propstat><D:prop><D:getcontenttype/><D:displayname>{$this->name}</D:displayname><D:getlastmodified>{$this->lastmodified}</D:getlastmodified><D:resourcetype><D:collection/></D:resourcetype></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>";
        } else if ($this->fileType == 2) {
            $this->xml = "<D:response><D:href>{$this->scriptName}/{$this->path}</D:href><D:propstat><D:prop><D:displayname>{$this->name}</D:displayname><D:getlastmodified>{$this->lastmodified}</D:getlastmodified><D:resourcetype><D:file /></D:resourcetype><D:getcontentlength>{$this->contentSize}</D:getcontentlength><D:getcontenttype>{$this->contenttype}</D:getcontenttype></D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat></D:response>";
        }
    }
    function propfind($depth = 0)
    {
        if ($depth && $this->fileType == 1) {
            $childXMLs = '';
            $childItems = scandir($this->realPath);
            foreach ($childItems as $childItemName) {
                if ($childItemName == '.' || $childItemName == '..') {
                    continue;
                }
                if ($this->path) {
                    $childItem = new item($this->path . '/' . $childItemName);
                } else {
                    $childItem = new item($childItemName);
                }
                $childXMLs .= $childItem->xml;
            }
            echo  '<?xml version="1.0" encoding="UTF-8"?><D:multistatus xmlns:D="DAV:">' . $this->xml . $childXMLs . '</D:multistatus>';
            http_response_code(207);
        } else if ($this->fileType == 1 || $this->fileType == 2) {
            echo '<?xml version="1.0" encoding="UTF-8"?><D:multistatus xmlns:D="DAV:">' . $this->xml . '</D:multistatus>';
            http_response_code(207);
        } else {
            http_response_code(404);
        }
    }
    function delete()
    {
        //是个目录
        if ($this->fileType == 1) {
            if (deleteChilds($this->realPath)) {
                http_response_code(200);
            } else {
                http_response_code(500);
            }
        } else if ($this->fileType == 2) {
            unlink($this->realPath);
            http_response_code(200);
        } else {
            http_response_code(404);
        }
    }
    function move()
    {
        $dest = substr(strstr($_SERVER['HTTP_DESTINATION'], $_SERVER['SCRIPT_NAME']), strlen($_SERVER['SCRIPT_NAME']) + 1);
        if ($dest) {
            if (rename($this->realPath, $this->rootDir . '/' . $dest)) {
                http_response_code(200);
            } else {
                http_response_code(500);
            }
        } else {
            http_response_code(400);
        }
    }
    function put()
    {
        // 这是一种写法
        // if ($data = file_get_contents('php://input')) {
        //     if (file_put_contents($this->realPath, $data)) {
        //         http_response_code(200);
        //     } else {
        //         http_response_code(500);
        //     }
        // } else {
        //     http_response_code(500);
        // }

        //这是另一种写法
        $input = fopen('php://input', 'rb');
        $tempPath = $this->realPath . '.tmp';
        $temp = fopen($tempPath, 'wb');
        $bytes = 0;
        while ($data = fread($input, 1024)) {
            $bytes += strlen($data);
            fwrite($temp, $data);
        }
        fclose($input);
        fclose($temp);
        rename($tempPath, $this->realPath);
        http_response_code(201);
    }
    function get()
    {
        $realPath = $this->realPath;
        if (is_file($realPath)) {
            readfile($realPath);
        } else {
            http_response_code(404);
        }
    }
    function mkcol()
    {
        $realPath = $this->realPath;
        mkdir($realPath, 0777, true);
    }
    function copy()
    {
        $dest = substr(strstr($_SERVER['HTTP_DESTINATION'], $_SERVER['SCRIPT_NAME']), strlen($_SERVER['SCRIPT_NAME']) + 1);
        //复制的目的地应该是个文件路径,不能是根目录
        if (!$dest) {
            http_response_code(400);
        }
        // 已经存在了
        if (is_dir($this->rootDir . '/' . $dest) || is_file($this->rootDir . '/' . $dest)) {
            http_response_code(400);
            exit;
        }
        if ($this->fileType == 2) {
            //这是copy一个文件
            if (copy($this->realPath, $this->rootDir . '/' . $dest)) {
                http_response_code(201);
            } else {
                http_response_code(500);
            }
        } else  if ($this->fileType == 1) {
            //文件夹
            if (copyChilds($this->realPath, $this->rootDir . '/' . $dest)) {
                http_response_code(201);
            } else {
                http_response_code(500);
            }
        } else {
            http_response_code(404);
        }
    }
}
function deleteChilds($parentName)
{
    if (!is_dir($parentName)) {
        return false;
    }
    foreach (scandir($parentName) as $childName) {
        if ($childName == '.' || $childName == '..') {
            continue;
        }
        if (is_file($parentName . '/' . $childName)) {
            unlink($parentName . '/' . $childName);
        } else if (is_dir($parentName . '/' . $childName)) {
            deleteChilds($parentName . '/' . $childName);
            rmdir($parentName . '/' . $childName);
        }
    }
    rmdir($parentName);
    return true;
}
function copyChilds($srcParentPath, $destPath)
{
    if (!is_dir($srcParentPath)) {
        return false;
    }
    mkdir($destPath);
    foreach (scandir($srcParentPath) as $childName) {
        if ($childName == '.' || $childName == '..') {
            continue;
        }
        if (is_file($srcParentPath . '/' . $childName)) {
            copy($srcParentPath . '/' . $childName, $destPath . '/' . $childName);
        } else if (is_dir($srcParentPath . '/' . $childName)) {
            mkdir($destPath . '/' . $childName);
            copyChilds($srcParentPath . '/' . $childName, $destPath . '/' . $childName);
        }
    }
    return true;
}
// 身份验证
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] != $username || $_SERVER['PHP_AUTH_PW'] != $password) {
    header('WWW-Authenticate: Basic realm="WebDAV Secure Area"');
    http_response_code(401);
    exit;
}
//ROOT_DIR
$ROOT_DIR = 'public';
//depth
if (isset($_SERVER['HTTP_DEPTH'])) {
    $depth = $_SERVER['HTTP_DEPTH'];
} else {
    $depth = 0;
}
//path
if (isset($_GET['path'])) {
    $queryPath = $_GET['path'];
} else {
    $queryPath = '';
}
try {
    if ($_SERVER['REQUEST_METHOD'] == 'PROPFIND') {
        header('Content-Type: application/xml; charset=utf-8');
        (new item($queryPath))->propfind($depth);
    } else if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        header('Allow: OPTIONS, GET, MOVE, PUT, DELETE, PROPFIND');
        header('DAV: 1,2,3');
        http_response_code(200);
    } else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        (new item($queryPath))->get();
    } else if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        (new item($queryPath))->put();
    } else if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        (new item($queryPath))->delete();
    } else if ($_SERVER['REQUEST_METHOD'] == 'MOVE') {
        (new item($queryPath))->move();
    } else if ($_SERVER['REQUEST_METHOD'] == 'MKCOL') {
        (new item($queryPath))->mkcol();
    } else if ($_SERVER['REQUEST_METHOD'] == 'COPY') {
        (new item($queryPath))->copy();
    } else {
        http_response_code(405);
    }
} catch (Exception $e) {
    echo $e;
    http_response_code(500);
}
