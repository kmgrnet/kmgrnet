# 后续接入建议

## 1. 接入真实 Likeshop
- 将官方 Likeshop 源码放入 /opt/kmgrnet/likeshop。
- 通过 api.kmgrnet.com/install 初始化数据库。
- 配置 MySQL 和 Redis 连接参数。

## 2. 接入支付渠道
- 微信支付：配置商户号、API v3 密钥、证书文件。
- 支付宝：配置应用 ID、应用私钥、支付宝公钥。
- 银联：配置商户号和证书文件。

## 3. 补齐生产环境
- 为真实域名申请正式 SSL 证书。 
- 在 Cloudflare 配置 DNS 与 WAF 放行回调地址。
- 将 localhost:8443 替换为正式域名。

## 4. 下一步可做的功能
- 订单落库。
- 支付回调状态更新。
- 前端轮询订单状态并展示支付成功页面。
