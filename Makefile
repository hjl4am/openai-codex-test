.PHONY: up down logs ps mock-test

up:
	docker compose up -d


down:
	docker compose down -v

logs:
	docker compose logs -f wordpress mock-llm

ps:
	docker compose ps

mock-test:
	curl -sS http://localhost:11434/v1/chat/completions \
	  -H 'Content-Type: application/json' \
	  -d '{"model":"mock-model","messages":[{"role":"user","content":"请改写：测试文本"}]}' | jq .
