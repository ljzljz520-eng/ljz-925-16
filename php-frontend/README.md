# PHP Frontend - 卡密访问控制系统

这是当前使用中的 PHP 前端实现，包含卡密验证、主页展示与认证状态管理。

## 项目结构

```
php-frontend/
├── public/              # Web根目录
│   ├── index.php       # 主路由文件
│   ├── api.php         # API代理文件
│   └── .htaccess       # Apache URL重写规则
├── includes/           # PHP页面模板
│   ├── gate.php        # 卡密验证页面
│   └── home.php        # 主页面
└── assets/             # 静态资源
    ├── css/
    │   └── styles.css  # 样式文件（Tailwind风格）
    └── js/
        ├── utils.js    # 工具函数
        ├── gate.js     # 卡密验证页面逻辑
        └── home.js     # 主页面逻辑
```

## 功能特性

### ✅ 已实现的功能

1. **卡密验证页面 (/gate)**
   - 12位卡密输入
   - 自动转大写
   - 前端表单验证
   - 加载状态显示
   - 错误提示
   - Toast通知

2. **主页面 (/home)**
   - 验证成功展示
   - 显示有效期
   - 30秒心跳检测
   - 自动登出（token失效时）
   - 手动退出登录

3. **认证系统**
   - localStorage管理
   - Token存储
   - 自动过期检测
   - 路由守卫

4. **UI/UX**
   - 完整覆盖当前前端所需的界面与交互
   - Tailwind风格的CSS类
   - 响应式设计
   - 动画效果（加载动画、Toast滑入）
   - 防调试保护（禁用F12、右键等）

5. **API集成**
   - 通过API入口代理后端（同项目直连或外部API_BASE_URL）
   - 统一错误处理
   - 自动添加Authorization头

## 安装和配置

### 1. 环境要求

- PHP 7.4+
- Apache (with mod_rewrite)
- 同项目部署时自动直连后端；独立部署时需要可访问的后端API

### 2. 配置步骤

1. 将 `php-frontend` 目录放到Web服务器根目录
2. 确保Apache已启用 `mod_rewrite` 模块
3. 配置虚拟主机，将DocumentRoot指向 `php-frontend/public`
4. 如使用外部后端API，修改 `includes/config.php` 中的 `API_BASE_URL`

### 3. Apache虚拟主机配置示例

```apache
<VirtualHost *:80>
    ServerName card-code.local
    DocumentRoot "/path/to/php-frontend/public"

    <Directory "/path/to/php-frontend/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 4. 使用PHP内置服务器（开发环境）

```bash
cd php-frontend/public
php -S localhost:3000
```

注意：使用PHP内置服务器时，需要手动处理路由。建议使用Apache。

## 技术实现细节

### 1. 路由系统

- 使用 `.htaccess` 实现URL重写
- `index.php` 作为前端控制器
- 支持 `/gate` 和 `/home` 路由
- 自动重定向未认证用户

### 2. 认证机制

- 使用localStorage存储token和过期时间
- API请求自动携带Authorization
- 客户端30秒心跳检测
- token过期自动跳转

### 3. API代理

- `api.php` 作为API入口
- 同项目部署时直接调用后端入口
- 外部部署时使用cURL转发到API_BASE_URL
- 自动透传Authorization头
- 统一错误处理

### 4. 样式系统

- 自定义CSS实现Tailwind风格的工具类
- 支持渐变背景、阴影、动画等
- 响应式设计
- 100%还原原UI效果

### 5. JavaScript功能

- 模块化设计（utils.js, gate.js, home.js）
- Toast通知系统
- API客户端封装
- 表单验证工具
- 加载状态管理

## 测试验证

### 功能测试清单

- [x] 访问根路径自动重定向
- [x] 卡密验证页面正常显示
- [x] 输入自动转大写
- [x] 前端验证（空值、长度）
- [x] 提交卡密并验证
- [x] 验证成功跳转到主页
- [x] 主页显示有效期
- [x] 心跳检测正常工作
- [x] 手动退出登录
- [x] Token失效自动跳转
- [x] Toast通知正常显示
- [x] 防调试功能正常
- [x] 响应式布局正常

## 注意事项

1. **localStorage**：确保浏览器允许本地存储
2. **CORS问题**：如果后端API在不同域名，需要配置CORS
3. **HTTPS**：生产环境建议使用HTTPS
4. **错误日志**：检查PHP错误日志以排查问题
5. **性能优化**：可以添加缓存、压缩等优化

## 故障排除

### 1. 页面404错误

- 检查 `.htaccess` 是否生效
- 确认Apache已启用 `mod_rewrite`
- 检查虚拟主机配置

### 2. API调用失败

- 检查后端API是否运行
- 确认 `$API_BASE_URL` 配置正确
- 查看PHP错误日志

### 3. localStorage问题

- 清除浏览器本地存储后重试
- 确认登录后已写入token

### 4. 样式不显示

- 检查 `/assets/css/styles.css` 路径
- 确认文件权限
- 清除浏览器缓存

## 总结

当前 PHP 前端实现包含以下能力：

- ✅ 完整的路由系统
- ✅ 认证和授权
- ✅ 心跳检测
- ✅ Toast通知
- ✅ 表单验证
- ✅ 加载状态
- ✅ 防调试保护
- ✅ 响应式UI
- ✅ 所有动画效果

可以直接部署使用，无需任何构建工具。
