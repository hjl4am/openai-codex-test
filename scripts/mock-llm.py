import json
from http.server import BaseHTTPRequestHandler, HTTPServer

HOST = "0.0.0.0"
PORT = 11434


class Handler(BaseHTTPRequestHandler):
    def _send(self, code: int, payload: dict):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self):
        if self.path != "/v1/chat/completions":
            self._send(404, {"error": "not found"})
            return

        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length)

        try:
            payload = json.loads(raw.decode("utf-8"))
        except Exception:
            self._send(400, {"error": "invalid json"})
            return

        user_text = ""
        for msg in payload.get("messages", []):
            if msg.get("role") == "user":
                user_text = msg.get("content", "")
                break

        rewritten = {
            "title": "[AI改写示例] 标题已重写",
            "content": "这是 mock-llm 返回的改写内容。\n\n原始请求片段：\n" + user_text[:300],
        }

        response = {
            "id": "mock-chatcmpl-001",
            "object": "chat.completion",
            "created": 1710000000,
            "model": payload.get("model", "mock-model"),
            "choices": [
                {
                    "index": 0,
                    "message": {
                        "role": "assistant",
                        "content": json.dumps(rewritten, ensure_ascii=False),
                    },
                    "finish_reason": "stop",
                }
            ],
        }

        self._send(200, response)


if __name__ == "__main__":
    print(f"Mock LLM listening on http://{HOST}:{PORT}")
    HTTPServer((HOST, PORT), Handler).serve_forever()
