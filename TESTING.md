# WordPress 本地测试模板（可直接给 Codex 使用）

## 1. 启动环境

```bash
make up
make ps
```

访问地址：
- WordPress: `http://localhost:8080`
- 管理后台: `http://localhost:8080/wp-admin`
- Mock LLM API: `http://localhost:11434/v1/chat/completions`

> 首次打开 WordPress 时请完成安装向导，并记录管理员账号密码。

## 2. 激活插件

1. 登录后台 `wp-admin`。
2. 进入 **插件** 页面。
3. 启用 **WordPress AI Rewriter**。
4. 点击插件行里的 **设置**，进入插件配置页。

## 3. 填写插件配置（统一后台配置）

在插件后台填入以下值并保存：

- `api_endpoint`: `http://mock-llm:11434/v1/chat/completions`
- `api_key`: `mock-key`
- `model`: `mock-model`
- `system_prompt`: `你是一位中文编辑，请输出 JSON。`

> 说明：因为 WordPress 在容器内运行，访问 mock 服务要使用容器名 `mock-llm`，不是 `localhost`。

## 4. 功能验收步骤

1. 新建一篇文章，填入任意标题和正文。
2. 在右侧 **AI 文章改写** 面板点击 **AI 改写文章**。
3. 出现“文章已改写并保存”后刷新页面。
4. 验证标题和正文已被更新。

## 5. API 可用性快速检查

```bash
make mock-test
```

如果返回 `choices[0].message.content` 且内容是 JSON 字符串，说明 mock API 可用。

## 6. 常见问题

- 文章改写按钮无响应：确认正在编辑的是 `post` 类型文章。
- 提示“请先在插件设置中完成 API 配置”：检查 `api_endpoint/api_key/model/system_prompt` 是否均已保存。
- 接口请求失败：执行 `make logs` 查看 `mock-llm` 和 `wordpress` 日志。

## 7. 停止环境

```bash
make down
```
