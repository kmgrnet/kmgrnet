# 安装与卸载指南

本文档描述 kmgrnet 项目的完整安装流程，覆盖前端（Cloudflare Pages）与后端（VPS + Docker），并说明如已部署过、需要完整卸载后重新部署时应如何操作。

## 架构概览

- **前端**：`web_pc/`（PC 站）与 `web_h5/`（H5 站），静态文件，通过 Cloudflare Pages 部署。
- **后端**：`likeshop/public/index.php`，运行在 VPS 上的 Docker Compose 栈中，包含 `nginx` + `php` + `mysql` + `redis` 四个容器，域名 `api.kmgrnet.com` 反代到 PHP 服务。
- 前后端通过 `API_BASE=https://api.kmgrnet.com` 关联，前端所有请求都打到该域名。

---

## 一、前端安装（Cloudflare Pages）

### 1.1 首次安装

1. 代码推送到 GitHub 仓库 `kmgrnet/kmgrnet`（见本文末尾"推送到 GitHub"）。
2. 登录 [Cloudflare Dashboard](https://dash.cloudflare.com/)，进入 **Workers & Pages**。
3. 点击 **Create application** -> **Pages** -> **Connect to Git**，选择仓库 `kmgrnet/kmgrnet`，分支 `main`。
4. 构建设置：
   - 构建命令：留空（纯静态文件，无需构建）
   - 输出目录：`web_pc`（PC 站）。如需同时发布 H5 站，需另建一个 Pages 项目，输出目录指向 `web_h5`，或使用同一项目的多路径路由，视你的 DNS 规划而定。
5. 环境变量：新增 `API_BASE=https://api.kmgrnet.com`，可选 `NODE_ENV=production`。
6. 保存并触发首次部署。
7. 在 Cloudflare DNS 中为对应域名（`kmgrnet.com` / `m.kmgrnet.com`）绑定该 Pages 项目的自定义域。

### 1.2 验证

1. 打开 Pages 站点首页，确认静态资源和 JS 正常加载。
2. 打开浏览器开发者工具 Network，下单/操作后确认请求地址为 `https://api.kmgrnet.com/...`，且返回 JSON（不是 405 / 521 / HTML 页面）。

### 1.3 完整卸载（前端）

如需从 Cloudflare 完全移除已部署的前端：

1. Cloudflare Dashboard -> **Workers & Pages** -> 选中对应项目。
2. **Settings** -> 拉到底部 **Delete project**，输入项目名确认删除。此操作会删除该项目全部历史部署版本，不可恢复。
3. 如绑定了自定义域名，进入 **DNS** 设置，删除指向该 Pages 项目的 CNAME 记录（如 `kmgrnet.com`、`m.kmgrnet.com`）。
4. 如有遗留的 Page Rules / Redirect Rules 单独引用了旧项目，一并清理。

### 1.4 重新部署（前端）

按 1.1 的步骤重新创建 Pages 项目并重新绑定域名即可，无需对代码仓库做任何改动。

---

## 二、后端安装（VPS + Docker）

### 2.1 前置条件

- 一台可访问公网的 VPS，已开放 80/443 端口。
- 已安装 Docker 与 Docker Compose：

```bash
apt-get update
apt-get install -y docker.io
systemctl enable --now docker
docker --version
docker compose version
```

- 已生成或获取 `kmgrnet.com` 的 SSL 证书（`.crt` / `.key`）。

### 2.2 首次安装

```bash
# 1. 克隆仓库
cd ~
git clone https://github.com/kmgrnet/kmgrnet.git kmgrnet
cd ~/kmgrnet

# 2. 放置证书
# 将证书文件放到 nginx/ssl/kmgrnet.crt 和 nginx/ssl/kmgrnet.key

# 3. 配置环境变量
cp .env.example .env
nano .env
```

`.env` 需要配置的关键变量：

```env
MYSQL_ROOT_PASSWORD=<自定义强密码>
MYSQL_DATABASE=likeshop_db
REDIS_PASSWORD=<自定义强密码>

# 微信支付 v3 回调验签所需
WECHAT_API_V3_KEY=<商户平台 API安全 中的 32 位 APIv3 密钥>
WECHAT_PLATFORM_PUBLIC_KEY_PATH=/etc/wechat/certs/wechatpay_platform.pem
```

若启用微信支付回调验签，还需下载平台证书：

```bash
mkdir -p wechat/certs
# 从 https://api.mch.weixin.qq.com/v3/certificates 下载证书
# 保存为 wechat/certs/wechatpay_platform.pem
```

```bash
# 4. 构建并启动
docker compose up -d --build

# 5. 验证容器状态
docker compose ps
docker compose logs -f php
```

### 2.3 验证

```bash
curl -k -H 'Host: api.kmgrnet.com' https://127.0.0.1/health
curl -k -H 'Host: api.kmgrnet.com' https://127.0.0.1/api/orders/list
```

正常应看到：

```json
{"success":true,"code":0,"message":"ok","data":{"service":"kmgrnet-likeshop", "timestamp": "..."}}
```

### 2.4 完整卸载（后端）

**警告：以下操作会删除数据库和 Redis 中的全部数据，且不可恢复。执行前务必确认已完成必要的数据备份。**

```bash
cd ~/kmgrnet

# 1. 停止并移除容器、网络（不含数据卷，因为本项目用 bind mount 而非具名 volume）
docker compose down

# 2. 确认容器已全部停止移除（应无 kmgrnet-nginx / kmgrnet-php / kmgrnet-mysql / kmgrnet-redis）
docker ps -a | grep kmgrnet

# 3. 删除本地数据目录（数据库、Redis 持久化数据）
rm -rf mysql/data
rm -rf redis/data

# 4.（可选）删除已构建的镜像，释放磁盘空间
docker images | grep kmgrnet
docker rmi kmgrnet-php 2>/dev/null
docker image prune -f

# 5.（可选）删除敏感配置文件，避免残留密钥/证书
rm -f .env
rm -rf wechat/certs
rm -rf nginx/ssl/kmgrnet.crt nginx/ssl/kmgrnet.key

# 6.（可选）彻底删除整个项目目录
cd ~
rm -rf ~/kmgrnet
```

确认卸载彻底完成：

```bash
docker ps -a | grep kmgrnet   # 应无输出
docker images | grep kmgrnet  # 应无输出
```

### 2.5 重新部署（后端）

如卸载后需要重新部署，从 2.1 的"前置条件"开始，按 2.2 的步骤重新执行即可。关键点：

- 重新 `git clone` 仓库（若整个目录被删除）。
- 重新放置 SSL 证书、微信支付平台证书（这些文件已被 `.gitignore`，不会随代码仓库恢复，必须手动重新放置）。
- 重新创建 `.env` 并填入密码/密钥（`.env` 同样不在 git 仓库中）。
- 如果只是想清空数据重新来一遍、并非彻底删除目录，可跳过 2.4 中的第 3-5 步，只执行：

```bash
cd ~/kmgrnet
docker compose down
rm -rf mysql/data redis/data
git pull
docker compose up -d --build
```

---

## 三、推送到 GitHub

改动前端或后端代码后，需要先推送到 GitHub，Cloudflare Pages 才能拉取新代码；VPS 也需要 `git pull` 才能获取最新后端代码。

```bash
cd ~/kmgrnet
git add .
git commit -m "描述本次改动"
git push origin main
```

如果 GitHub 弹出用户名/密码输入：

- Username: 你的 GitHub 用户名
- Password: 你的 GitHub Personal Access Token（PAT），不是账号密码

推送后：

- Cloudflare Pages 若已连接 GitHub 仓库，会自动触发新部署。
- VPS 需要手动登录执行：

```bash
cd ~/kmgrnet
git pull
docker compose up -d --build
```

---

## 四、常见问题排查

- **前端请求返回 405**：可能仍在请求旧页面缓存，重新部署 Pages 项目并清除浏览器缓存。
- **前端请求返回 521**：Cloudflare 无法连接到 VPS origin，检查 `api.kmgrnet.com` 是否正确解析到 VPS IP，且 VPS 的 80/443 端口已开放并监听。
- **微信支付回调一直失败**：检查 `.env` 中 `WECHAT_API_V3_KEY` 是否已配置且正确；查看 `docker compose logs php` 中 `[wechat-notify]` 相关日志定位具体原因。
- **容器无法启动**：`docker compose logs <service>` 查看具体报错，常见原因是端口被占用（`80`/`443`）或 `.env` 中变量缺失。
- **nginx 容器起不来，报 `address already in use`**：说明宿主机 `80` 或 `443` 端口已被其他进程占用（`ss -tlnp | grep -E ':80 |:443 '` 排查占用者）。**不要**直接改 `docker-compose.yml` 里写死的端口号——这会影响所有部署环境。应在该台机器的 `.env` 中单独覆盖 `NGINX_HTTP_PORT` / `NGINX_HTTPS_PORT`（例如本机开发环境冲突时设 `NGINX_HTTP_PORT=8080`），公网生产 VPS 通常保持默认 `80`/`443` 即可，因为外部访问（含 Cloudflare 回源）默认按标准端口连接，非标准端口需要额外配置才能被外部访问。
