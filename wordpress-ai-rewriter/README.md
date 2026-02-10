# WordPress AI Rewriter 插件

该插件用于：

- 在 WordPress 插件管理后台提供统一的 AI 配置界面。
- 统一管理大模型 API 信息：`api_endpoint`、`api_key`、`model`、`system_prompt`。
- 在文章编辑页一键改写文章标题与正文。

## 安装

1. 将 `wordpress-ai-rewriter` 目录放到 `wp-content/plugins/`。
2. 在 WordPress 后台启用 **WordPress AI Rewriter** 插件。
3. 进入后台菜单 **AI 改写**（或插件列表中的“设置”链接），填写 API 配置并保存。

## 使用

1. 打开任意文章编辑页。
2. 在右侧 **AI 文章改写** 面板点击 **AI 改写文章**。
3. 等待接口返回后刷新页面查看更新内容。

## 注意

- 当前版本使用 OpenAI 兼容的 `chat/completions` 接口。
- 插件会要求模型按 JSON 输出：`{"title":"...","content":"..."}`。
- 建议在生产环境先使用草稿文章验证改写质量。

## 本地可运行测试环境模板

仓库根目录提供了 `docker-compose.yml`、`Makefile`、`scripts/mock-llm.py` 与 `TESTING.md`。
可直接按 `TESTING.md` 启动 WordPress + MySQL + Mock LLM 来做端到端测试。
