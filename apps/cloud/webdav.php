<?php
declare(strict_types=1);
define('CLOUD_WEBDAV_BOOT', true);
$cloudAutoloader = require_once __DIR__ . '/vendor/autoload.php';
if (!class_exists('Sabre\\DAV\\FS\\Directory')) {
    if (is_object($cloudAutoloader) && method_exists($cloudAutoloader, 'addPsr4')) {
        $cloudAutoloader->addPsr4('Sabre\\', __DIR__ . '/vendor/sabre/dav/lib');
    }
    if (!class_exists('Sabre\\DAV\\FS\\Directory')) {
        throw new RuntimeException('SabreDAV filesystem backend is not installed');
    }
}
require_once __DIR__ . '/api.php';
use Sabre\DAV;
use Sabre\DAV\FS;
final class CloudWebDavDirectory extends FS\Directory {
    private string $email;
    private string $rootPath;
    public function __construct(string $path, string $email, string $rootPath, ?string $overrideName = null) {
        parent::__construct($path, $overrideName);
        $this->email = $email;
        $this->rootPath = rtrim(str_replace('\\', '/', $rootPath), '/');
    }
    public function createFile($name, $data = null) {
        $etag = parent::createFile($name, $data);
        $this->changed();
        return $etag;
    }
    public function createDirectory($name) {
        parent::createDirectory($name);
        $this->changed();
    }
    public function getChild($name) {
        if ($this->isHiddenRootEntry((string)$name)) throw new DAV\Exception\NotFound('File with name ' . $name . ' could not be located');
        $path = $this->path . '/' . $name;
        if (!file_exists($path)) throw new DAV\Exception\NotFound('File with name ' . $path . ' could not be located');
        return is_dir($path)
            ? new self($path, $this->email, $this->rootPath)
            : new CloudWebDavFile($path, $this->email, $this->rootPath);
    }
    public function getChildren() {
        $nodes = [];
        $iterator = new FilesystemIterator($this->path, FilesystemIterator::CURRENT_AS_SELF | FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $entry) {
            if ($this->isHiddenRootEntry($entry->getFilename())) continue;
            $nodes[] = $this->getChild($entry->getFilename());
        }
        return $nodes;
    }
    public function childExists($name) {
        return !$this->isHiddenRootEntry((string)$name) && parent::childExists($name);
    }
    public function getQuotaInfo() {
        try {
            $absolute = realpath($this->path);
            if ($absolute === false) return [0, 0];
            $total = disk_total_space($absolute);
            $free = disk_free_space($absolute);
            if ($total === false || $free === false) return [0, 0];
            return [$total - $free, $free];
        } catch (Throwable $e) {
            return [0, 0];
        }
    }
    public function delete() {
        parent::delete();
        $this->changed();
    }
    public function setName($name) {
        parent::setName($name);
        $this->changed();
    }
    private function isHiddenRootEntry(string $name): bool {
        return rtrim(str_replace('\\', '/', $this->path), '/') === $this->rootPath && in_array($name, ['.archive', '.temp_files', '.documents', '.photos', '.pdf', '.trash', '.clientsync', '.cloud_groups'], true);
    }
    private function changed(): void {
        invalidate_file_tree($this->email);
    }
}
final class CloudWebDavFile extends FS\File {
    private string $email;
    public function __construct(string $path, string $email, string $rootPath = '') {
        parent::__construct($path);
        $this->email = $email;
    }
    public function put($data) {
        parent::put($data);
        invalidate_file_tree($this->email);
    }
    public function delete() {
        parent::delete();
        invalidate_file_tree($this->email);
    }
    public function setName($name) {
        parent::setName($name);
        invalidate_file_tree($this->email);
    }
}
$webdavEmail = '';
$basicUser = (string)($_SERVER['PHP_AUTH_USER'] ?? '');
$basicPass = (string)($_SERVER['PHP_AUTH_PW'] ?? '');
$authHeader = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($basicUser === '' && $authHeader !== '' && preg_match('/^Basic\s+(.+)$/i', $authHeader, $m)) {
    $decoded = base64_decode($m[1], true);
    if (is_string($decoded) && str_contains($decoded, ':')) {
        [$basicUser, $basicPass] = explode(':', $decoded, 2);
        $_SERVER['PHP_AUTH_USER'] = $basicUser;
        $_SERVER['PHP_AUTH_PW'] = $basicPass;
    }
}
if ($basicUser !== '' && validate_client_token_for_email($basicUser, $basicPass)) {
    $webdavEmail = $basicUser;
}
if ($webdavEmail === '') {
    http_response_code(401);
    header('WWW-Authenticate: Basic realm="AppDashboard Cloud WebDAV"');
    exit;
}
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '/webdav/';
if ($requestPath === '/webdav' || $requestPath === '/apps/cloud/webdav.php') {
    header('Location: ' . $requestPath . '/');
    exit;
}
$user = email_to_cloud_username($webdavEmail);
$root = user_root($user, 'clientsync');
if (!is_dir($root)) @mkdir($root, 0775, true);
$server = new DAV\Server(new CloudWebDavDirectory($root, $webdavEmail, $root));
$baseUri = str_starts_with(strtolower($requestPath), '/webdav') ? '/webdav/' : '/apps/cloud/webdav.php/';
$server->setBaseUri($baseUri);
$server->addPlugin(new DAV\Browser\Plugin());
$server->addPlugin(new DAV\Locks\Plugin(new DAV\Locks\Backend\File(upload_sessions_dir() . '/webdav_locks')));
$server->exec();
