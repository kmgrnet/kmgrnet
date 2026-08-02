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

常见配置：

```env
MYSQL_ROOT_PASSWORD=GuangRun_DB_Pass_2026
MYSQL_DATABASE=likeshop_db
REDIS_PASSWORD=GuangRun_Redis_Pass_2026
```

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

### 7. Cloudflare Pages 配置

在 Pages 项目环境变量中设置：

```text
API_BASE=https://api.kmgrnet.com
```

然后重新部署前端：

- 触发 Deploy
- 检查首页、下单、支付、后台等跳转是否能连通后端

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
