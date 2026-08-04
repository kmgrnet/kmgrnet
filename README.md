# 昆明光润网络科技有限公司

## 品牌门户与 Likeshop 多端电商结算系统实施方案

本工程为基于 Docker + Nginx + PHP + MySQL + Redis 的电商系统部署骨架，覆盖：

- PC 端门户：www.kmgrnet.com
- H5 端门户：m.kmgrnet.com
- API/后台入口：api.kmgrnet.com
- 微信小程序接入准备

## 目录结构

```text
/opt/kmgrnet/
├── docker-compose.yml
├── nginx/
│   └── conf.d/
│       └── site.conf
├── web_pc/
├── web_h5/
├── likeshop/
├── mysql/
│   └── data/
└── redis/
    └── data/
```

## 快速开始

1. 将本目录部署到服务器 /opt/kmgrnet。
2. 复制 .env.example 为 .env，并按需填写环境变量。
3. 生成证书文件并放置到 nginx/ssl/。
4. 运行：

apt-get update
apt-get install -y docker.io
systemctl enable --now docker
systemctl status docker --no-pager
docker info

ps -p 1 -o comm=
command -v systemctl || command -v service || command -v rc-service

apt-get update
apt-get install -y docker.io
service docker start
docker info

cd ~/kmgrnet
git clone https://github.com/kmgrnet/kmgrnet.git .

```bash
docker compose up -d
```

5. 访问：

- https://www.kmgrnet.com
- https://m.kmgrnet.com
- https://api.kmgrnet.com/install

## 正常部署流程（VPS + Docker + Cloudflare）

以下为已验证可用的部署步骤，适用于 VPS 上直接运行。

### 1. 服务器准备

```bash
# 进入项目目录
cd /root/kmgrnet

# 复制环境变量模板
cp .env.example .env
```

如果需要，可以编辑 `.env`：

```bash
nano .env
```

cd ~/kmgrnet
docker compose up -d --build

常见配置：

```env
MYSQL_ROOT_PASSWORD=GuangRun_DB_Pass_2026
MYSQL_DATABASE=likeshop_db
REDIS_PASSWORD=GuangRun_Redis_Pass_2026

# 微信支付 v3 回调验签所需，见下方「微信支付回调配置」
WECHAT_API_V3_KEY=
WECHAT_PLATFORM_PUBLIC_KEY_PATH=/etc/wechat/certs/wechatpay_platform.pem
```

#### 微信支付回调配置

`likeshop/public/index.php` 中 `/api/payment/notify/wechat` 回调会校验微信支付平台签名并用 APIv3 密钥解密，上线前必须配置：

1. `WECHAT_API_V3_KEY`：商户平台 -> 账户设置 -> API安全 中设置的 32 位 APIv3 密钥，必填，缺失时回调会直接报错拒绝。
2. 微信支付平台证书公钥：从 `GET https://api.mch.weixin.qq.com/v3/certificates` 下载并定期轮换，保存为 `wechat/certs/wechatpay_platform.pem`（该目录已加入 `.gitignore`，不会被提交）。`WECHAT_PLATFORM_PUBLIC_KEY_PATH` 是容器内路径，对应 `docker-compose.yml` 中 `./wechat/certs:/etc/wechat/certs:ro` 的挂载。
   - 若证书暂未配置，验签会被跳过并记录警告日志，仅依赖 APIv3 密钥解密作为最低限度防护，**必须尽快补齐**。

### 2. 确认 Docker 已安装并可用

```bash
docker --version
docker compose version
```

若出现 `Cannot connect to the Docker daemon`，执行：

```bash
systemctl start docker
systemctl enable docker
```

然后验证：

```bash
docker info
```

### 3. 解决端口冲突

如果 8080 已被其他程序占用，例如 `sing-box`，需要先停止它：

```bash
ss -lntp | grep 8080
kill -9 766386
```

如果需要更稳妥地避免冲突，使用标准端口映射：

```yaml
ports:
  - "80:80"
  - "443:443"
```

对应的 `docker-compose.yml` 中应为：

```yaml
services:
  nginx:
    image: nginx:1.24-alpine
    container_name: kmgrnet-nginx
    restart: always
    ports:
      - "80:80"
      - "443:443"
```

### 4. 启动项目
docker-compose.yml
'''
services:
  nginx:
    image: nginx:1.24-alpine
    container_name: kmgrnet-nginx
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - ./nginx/ssl:/etc/nginx/ssl:ro
      - ./web_pc:/usr/share/nginx/html/pc:ro
      - ./web_h5:/usr/share/nginx/html/h5:ro
      - ./likeshop:/var/www/html/likeshop
    depends_on:
      - php
      - mysql
      - redis
    networks:
      - kmgrnet-network

  php:
    build:
      context: .
      dockerfile: docker/php.Dockerfile
    container_name: kmgrnet-php
    restart: always
    working_dir: /var/www/html/likeshop
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-GuangRun_DB_Pass_2026}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-likeshop_db}
      DB_HOST: mysql
      DB_PORT: 3306
      DB_USERNAME: root
    volumes:
      - ./likeshop:/var/www/html/likeshop
    networks:
      - kmgrnet-network

  mysql:
    image: mysql:8.0
    container_name: kmgrnet-mysql
    restart: always
    command: --default-authentication-plugin=mysql_native_password
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-GuangRun_DB_Pass_2026}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-likeshop_db}
    volumes:
      - ./mysql/data:/var/lib/mysql
    networks:
      - kmgrnet-network

  redis:
    image: redis:7.0-alpine
    container_name: kmgrnet-redis
    restart: always
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD:-GuangRun_Redis_Pass_2026}
    volumes:
      - ./redis/data:/data
    networks:
      - kmgrnet-network

networks:
  kmgrnet-network:
    driver: bridge

```bash
cd /root/kmgrnet
docker compose down
docker compose up -d --build
```

查看容器状态：

```bash
docker compose ps
```

正常应看到：

- kmgrnet-php
- kmgrnet-mysql
- kmgrnet-redis
- kmgrnet-nginx

### 5. 验证后端接口

本机验证：

```bash
curl -k -H 'Host: api.kmgrnet.com' https://127.0.0.1/health
curl -k -H 'Host: api.kmgrnet.com' https://127.0.0.1/api/orders/list
```

如果返回 JSON，说明后端正常：

```json
{"success":true,"code":0,"message":"ok","data":{"service":"kmgrnet-likeshop"}}
```

### 6. Cloudflare 域名配置

在 Cloudflare 中添加域名：

- `api.kmgrnet.com` -> 指向 VPS IP

建议先测试：

- Proxy status 设置为 `DNS only`

验证成功后，再切回 `Proxied`。

### 7. Cloudflare Pages 前端部署

#### 7.1 代码提交到 GitHub

```bash
cd /root/kmgrnet
git config user.name "kmgrnet"
git config user.email "kmgrnet@local"
git add .
git commit -m "fix: add cors support and cloudflare api base"
git branch -M main
git remote set-url origin https://github.com/kmgrnet/kmgrnet.git
git push -u origin main
```

如果 GitHub 弹出用户名/密码输入：

- Username: 你的 GitHub 用户名
- Password: 你的 GitHub Personal Access Token（PAT）

#### 7.2 在 Cloudflare Pages 中创建/绑定项目

1. 登录 Cloudflare Dashboard
2. 进入 Workers & Pages
3. 点击 Create application
4. 选择 Pages
5. 连接 GitHub 仓库：`kmgrnet/kmgrnet`
6. 选择分支：`main`
7. 设置构建命令：

```bash
# 若使用静态文件部署，通常无需额外构建命令
```

8. 设置输出目录：

```text
web_pc
```

如果项目是直接托管 `web_pc` 目录，则输出目录应设为 `web_pc`。

#### 7.3 配置环境变量

在 Pages 项目设置中新增环境变量：

```text
API_BASE=https://api.kmgrnet.com
```

可以同时保留：

```text
NODE_ENV=production
```

#### 7.4 部署并验证

部署完成后：

1. 打开 Pages 站点首页
2. 确认前端 JS 可以加载
3. 点击下单/支付
4. 检查浏览器 Network，确保请求地址为：

```text
https://api.kmgrnet.com/api/order/create
```

5. 确认返回 JSON，而不是 405 / 521 / HTML

#### 7.5 常见问题排查

- 如果仍报 `405`：检查是否仍在请求旧页面代码，重新部署并清缓存
- 如果报 `521`：说明 Cloudflare 仍连不到 VPS origin，确保 `api.kmgrnet.com` 解析到 VPS，且颁发 443/80 端口
- 如果报 `Failed to execute 'json'`：检查是否返回了 HTML 或重定向，确保 `API_BASE` 正确

### 8. 常见问题

#### 8.1 端口被占用

```bash
ss -lntp | grep 8080
kill -9 <pid>
```

#### 8.2 Docker daemon 未启动

```bash
systemctl start docker
systemctl enable docker
```

#### 8.3 Cloudflare 返回 521

通常是因为 origin 没有监听标准端口：

```yaml
ports:
  - "80:80"
  - "443:443"
```

#### 8.4 前端报 `SyntaxError: Failed to execute 'json'`

说明返回内容不是标准 JSON，通常是请求地址错误或被重定向。重点检查：

- `API_BASE`
- `api.kmgrnet.com`
- Cloudflare 代理状态
- Nginx 443/80 是否正常监听

## 说明

- 本项目为昆明光润网络科技有限公司门户与 Likeshop 结算系统的实施工程。
- 生产环境中请替换为正式域名、SSL 证书、支付密钥和数据库凭据。
- 本文档记录的是已验证可运行的 VPS 部署方案，适合后续持续维护与扩展。
