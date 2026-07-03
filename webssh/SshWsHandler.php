<?php
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
class SshWsHandler implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, ['ssh' => null, 'auth' => false, 'halt' => false]);
    }
    public function onMessage(ConnectionInterface $conn, $msg): void
    {
        if (!$this->clients->contains($conn)) {
            return;
        }
        $state = $this->clients[$conn];
        if (!empty($state['halt'])) {
            return;
        }
        $data = json_decode((string)$msg, true);
        if (!is_array($data) || empty($data['type'])) {
            return;
        }
        $type = (string)$data['type'];
        if ($type === 'auth') {
            $this->handleAuth($conn, $state, $data);
            return;
        }
        if (!$state['auth'] || !($state['ssh'] instanceof \phpseclib3\Net\SSH2)) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Not authenticated']);
            return;
        }
        $ssh = $state['ssh'];
        if ($type === 'input') {
            if (!$ssh->isShellOpen()) {
                $this->failSession($conn, 'Shell is not open');
                return;
            }
            try {
                $ssh->write((string)($data['data'] ?? ''));
            } catch (\Throwable $e) {
                $this->failSession($conn, apSshEnsureUtf8($e->getMessage()));
            }
            return;
        }
        if ($type === 'resize' && $ssh->isShellOpen()) {
            $cols = apSshSanitizeWin($data['cols'] ?? 80, 80, 20, 500);
            $rows = apSshSanitizeWin($data['rows'] ?? 24, 24, 5, 200);
            try {
                $ssh->setWindowSize($cols, $rows);
            } catch (\Throwable $e) {
            }
        }
    }
    public function poll(): void
    {
        foreach ($this->clients as $conn) {
            $state = $this->clients[$conn];
            if (!empty($state['halt']) || !$state['auth'] || !($state['ssh'] instanceof \phpseclib3\Net\SSH2)) {
                continue;
            }
            $ssh = $state['ssh'];
            if (!$ssh->isShellOpen()) {
                continue;
            }
            try {
                while (true) {
                    $chunk = $ssh->read('', \phpseclib3\Net\SSH2::READ_NEXT);
                    if ($chunk === false || $chunk === true || $chunk === null || $chunk === '') {
                        break;
                    }
                    $this->sendJson($conn, ['type' => 'output', 'data' => apSshEnsureUtf8((string)$chunk)]);
                }
            } catch (\Throwable $e) {
                $this->failSession($conn, apSshEnsureUtf8($e->getMessage()));
            }
        }
    }
    public function onClose(ConnectionInterface $conn): void
    {
        $this->closeSession($conn);
    }
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->failSession($conn, apSshEnsureUtf8($e->getMessage()));
    }
    private function handleAuth(ConnectionInterface $conn, array $state, array $data): void
    {
        if ($state['auth']) {
            $this->sendJson($conn, ['type' => 'connected', 'message' => 'Already connected']);
            return;
        }
        $token = trim((string)($data['token'] ?? ''));
        if ($token === '') {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Missing token']);
            return;
        }
        $cfg = apSshLoadWsToken($token);
        if ($cfg === null) {
            $this->sendJson($conn, ['type' => 'error', 'message' => 'Invalid or expired token']);
            return;
        }
        $cols = apSshSanitizeWin($data['cols'] ?? 80, 80, 20, 500);
        $rows = apSshSanitizeWin($data['rows'] ?? 24, 24, 5, 200);
        $res = apSshConnectShell($cfg, $cols, $rows);
        if (empty($res['success']) || !($res['ssh'] instanceof \phpseclib3\Net\SSH2)) {
            $this->sendJson($conn, ['type' => 'error', 'message' => (string)($res['error'] ?? 'SSH connection failed')]);
            return;
        }
        $state['ssh'] = $res['ssh'];
        $state['auth'] = true;
        $state['halt'] = false;
        $this->clients[$conn] = $state;
        $this->sendJson($conn, ['type' => 'connected', 'message' => 'Shell ready']);
    }
    private function failSession(ConnectionInterface $conn, string $message): void
    {
        if ($this->clients->contains($conn)) {
            $state = $this->clients[$conn];
            $state['halt'] = true;
            $this->clients[$conn] = $state;
        }
        $this->sendJson($conn, ['type' => 'error', 'message' => $message]);
        $this->closeSession($conn);
    }
    private function closeSession(ConnectionInterface $conn): void
    {
        if (!$this->clients->contains($conn)) {
            return;
        }
        $state = $this->clients[$conn];
        if ($state['ssh'] instanceof \phpseclib3\Net\SSH2) {
            try {
                $state['ssh']->disconnect();
            } catch (\Throwable $e) {
            }
        }
        $this->clients->detach($conn);
    }
    private function sendJson(ConnectionInterface $conn, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            $conn->send($json);
        }
    }
}
