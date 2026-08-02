# 订单与退款数据落库说明

## 现状
订单与退款数据已从早期的 JSON 文件（`likeshop/public/orders.json`，历史遗留，已废弃）迁移到 MySQL，由 `likeshop/public/index.php` 通过 PDO 读写。

## 表结构

### orders
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| order_id | VARCHAR(64) PK | 订单号，格式 `ORDER-XXXXXXXX` |
| pay_status | TINYINT(1) | 0=待支付，1=已支付 |
| pay_method | VARCHAR(32) | wechat / alipay |
| amount | DECIMAL(10,2) | 订单金额 |
| title | VARCHAR(255) | 商品/订单标题 |
| created_at / updated_at / paid_at | DATETIME | 时间戳 |
| channel | VARCHAR(64) | 实际支付渠道（回调时写入） |

### refunds
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | INT AUTO_INCREMENT PK | |
| order_id | VARCHAR(64) | 关联订单号（索引） |
| customer | VARCHAR(128) | 客户名称 |
| amount | DECIMAL(10,2) | 退款金额 |
| status | VARCHAR(32) | 待审核 / 退款中 / 已退款（索引） |
| reason | VARCHAR(255) | 退款原因 |
| created_at / updated_at | DATETIME | 时间戳 |

表结构由 `initDbSchema()` 在每次相关请求时以 `CREATE TABLE IF NOT EXISTS` 幂等创建，无需手工迁移脚本。

## 涉及接口
- `POST /api/order/create`：写入 `orders` 表
- `GET /api/orders/list`：从 `orders` 表读取并按状态/渠道/日期/金额过滤
- `GET /api/order/detail`：读取单条订单
- `GET /api/order/status`：读取订单支付状态
- `POST /api/payment/notify/{channel}`：更新 `pay_status`、`paid_at`、`channel`
- `GET /api/refunds/list`：读取 `refunds` 表（首次访问会写入演示种子数据）
- `POST /api/refunds/update`：新增或更新退款记录
- `GET /api/summary/reconciliation`：基于 `orders` + `refunds` 聚合营收/退款统计

## 验证方式
```bash
docker exec kmgrnet-mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" likeshop_db \
  -e "SELECT order_id, pay_status, amount, channel, created_at FROM orders ORDER BY created_at DESC LIMIT 5;"
```

## 已知限制 / 后续可做
- `refunds` 首次查询为空时会自动插入 4 条演示种子数据，生产环境上线前应清理或改为按需生成。
- 数据库连接凭据目前来自容器环境变量（`MYSQL_ROOT_PASSWORD` 等），生产环境建议改为专用业务账号而非 root。
- 尚未接入真实支付渠道的签名校验，`/api/payment/notify/{channel}` 目前信任请求体中的 `order_id` 直接标记为已支付，存在被伪造调用的风险，接入真实支付前必须补齐验签逻辑。
