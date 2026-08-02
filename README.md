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

## 说明

- 本项目为实施方案的工程起始骨架，适合后续接入 Likeshop、微信支付、支付宝与银联支付。
- 生产环境中请替换为正式域名、SSL 证书、支付密钥和数据库凭据。
