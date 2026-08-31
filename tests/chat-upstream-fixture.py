#!/usr/bin/env python3
"""Loopback-only cURL integration tests. No API key or database is used."""
import http.server
import json
import socket
import subprocess
import threading
from collections import Counter

counts = Counter()
class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass
    def do_POST(self):
        self.rfile.read(int(self.headers.get("Content-Length", 0)))
        case = self.path.strip("/")
        counts[case] += 1
        if case == "empty" or (case == "transient" and counts[case] == 1):
            self.close_connection = True
            self.connection.shutdown(socket.SHUT_RDWR)
            return
        status, content, retry = 200, "", None
        if case in ("partial", "partial_retry"):
            content = 'data: {"choices":[{"delta":{"content":"partial"}}]}\n\n'
        elif case in ("limited", "quota", "long_retry_after") or (case == "ratelimit" and counts[case] == 1):
            status = 429
            retry = "60" if case == "long_retry_after" else ("1" if case == "ratelimit" else "4")
            content = json.dumps({"error": {"message": "insufficient_quota" if case == "quota" else "rate limit"}})
        else:
            content = 'data: {"choices":[{"delta":{"content":"OK"}}]}\n\ndata: {"choices":[{"delta":{},"finish_reason":"stop"}]}\n\ndata: [DONE]\n\n'
        body = content.encode()
        self.send_response(status)
        self.send_header("Content-Type", "text/event-stream" if status == 200 else "application/json")
        self.send_header("Content-Length", str(len(body)))
        if retry:
            self.send_header("Retry-After", retry)
        self.end_headers()
        self.wfile.write(body)

php = r'''<?php
require getcwd() . '/vendor/autoload.php';
foreach (['app/Exceptions/AiRequestException.php','app/Services/ChatReliability.php','app/Services/FileService.php','app/Services/AiApiService.php'] as $file) require_once getcwd() . '/' . $file;
class FixtureRouter extends App\Services\AiProxyRouter {
    public function routesFor(App\Models\AiModel $model): array {
        return [['type'=>'direct','label'=>'fixture','proxy'=>null,'proxy_url'=>null]];
    }
    public function markDirectSuccess(App\Models\AiModel $model): void {}
    public function markDirectFailure(App\Models\AiModel $model, Throwable $error): void {}
}
$service = new App\Services\AiApiService(new App\Services\FileService(), new FixtureRouter());
$direct = new ReflectionMethod($service, 'requestStream');
$routed = new ReflectionMethod($service, 'streamWithRouting');
$out=[];
foreach (['ok','empty','partial','limited','transient','ratelimit','quota','long_retry_after','partial_retry'] as $case) {
    $model = new App\Models\AiModel([
        'key'=>'fixture', 'provider'=>'custom', 'model_mapping'=>'fixture',
        'api_key'=>'fixture-placeholder', 'endpoint'=>getenv('FIXTURE_BASE') . '/' . $case,
        'timeout_seconds'=>3, 'proxy_mode'=>'direct', 'supports_files'=>false,
    ]);
    $answer=''; $notices=[];
    $callback = static function ($chunk) use (&$answer) {$answer.=$chunk;};
    try {
        if (in_array($case,['ok','empty','partial','limited'],true)) {
            $direct->invoke($service,$model,$model->endpoint,'fixture-placeholder',
                ['model'=>'fixture','messages'=>[['role'=>'user','content'=>'test']]],
                ['type'=>'direct','label'=>'fixture','proxy'=>null,'proxy_url'=>null],$callback,3);
        } else {
            $routed->invoke($service,'test','fixture','system',[],[],[$model],$callback,
                static function($notice)use(&$notices){$notices[]=$notice;});
        }
        $out[$case]=['ok'=>true,'answer'=>$answer,'notices'=>$notices];
    } catch (Throwable $e) {
        $out[$case]=['ok'=>false,'answer'=>$answer,'class'=>get_class($e),
            'curl'=>$e instanceof App\Exceptions\AiRequestException?$e->curlErrno:null,
            'http'=>$e instanceof App\Exceptions\AiRequestException?$e->httpCode:null,
            'retry_after'=>$e instanceof App\Exceptions\AiRequestException?$e->retryAfterSeconds:null];
    }
}
echo json_encode($out);
'''
server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
thread = threading.Thread(target=server.serve_forever, daemon=True)
thread.start()
try:
    import os
    env = dict(os.environ)
    env["FIXTURE_BASE"] = "http://127.0.0.1:" + str(server.server_port)
    result = subprocess.run(["php"], input=php, text=True, capture_output=True, env=env, timeout=40)
    if result.returncode:
        raise AssertionError(result.stderr)
    data = json.loads(result.stdout)
    assert data["ok"]["ok"] and data["ok"]["answer"] == "OK", data
    assert data["empty"]["curl"] == 52, data
    assert not data["partial"]["ok"] and data["partial"]["answer"] == "partial", data
    assert data["limited"]["http"] == 429 and data["limited"]["retry_after"] == 4, data
    assert data["transient"]["ok"] and counts["transient"] == 2, data
    assert data["ratelimit"]["ok"] and counts["ratelimit"] == 2, data
    assert not data["quota"]["ok"] and counts["quota"] == 1, data
    assert not data["long_retry_after"]["ok"] and counts["long_retry_after"] == 1, data
    assert not data["partial_retry"]["ok"] and counts["partial_retry"] == 1, data
    print(json.dumps({"passed": 9, "loopback_requests": dict(counts)}, sort_keys=True))
finally:
    server.shutdown()
    server.server_close()
