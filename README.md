# Amazon Connect Chat API PHP

基于Amazon Connect Chat API的PHP聊天服务器实现，包含HTML前端界面。

## 功能特性

- 启动聊天会话
- 实时WebSocket消息接收
- 发送和接收消息
- 会话恢复（页面刷新后自动恢复）
- 支持打字状态和参与者事件
- 简洁的HTML前端界面

## 安装步骤

1. 安装依赖：
```bash
composer install
```

2. 启动本地服务器：
```bash
php -S localhost:8080
```

3. 在浏览器中打开：`http://localhost:8080/chat.html`

## 使用方法

1. 在页面中输入您的Amazon Connect Contact Flow ID、Instance ID和显示名称
2. 点击"开始聊天"按钮
3. 在输入框中输入消息并发送
4. 实时接收客服回复和状态更新
5. 点击"结束聊天"按钮结束会话
6. 页面刷新后会自动恢复未结束的聊天会话

## 文件说明

- `chat-server.php` - PHP聊天服务器，处理Amazon Connect API调用
- `chat.html` - HTML前端界面，支持WebSocket实时通信
- `composer.json` - Composer依赖配置
- `load-env.php` - 环境变量加载器

## 注意事项

- 确保您的AWS账户有Amazon Connect的相关权限
- AWS凭证通过环境变量或IAM角色自动加载
- 需要有效的Contact Flow ID和Instance ID
- WebSocket连接用于实时消息接收
- 建议在生产环境中使用HTTPS

## 测试页面
<img width="1676" alt="Image" src="https://github.com/user-attachments/assets/9c93d961-5c90-4e12-96b0-a74a8a2783be" />
