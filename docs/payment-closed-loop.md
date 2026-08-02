# 支付闭环说明

## 已实现
- 订单创建接口：/api/order/create
- 订单状态查询接口：/api/order/status
- 支付回调接口：/api/payment/notify/{channel}
- 前端已在 PC/H5 页面内触发订单创建与状态轮询

## 运行方式
1. 启动容器：docker compose up -d
2. 访问首页：https://localhost:8443/
3. 点击任一可购买商品，进入支付弹窗
4. 选择支付方式并确认支付
5. 系统将自动创建订单、触发回调并轮询状态

## 说明
- 订单与退款数据已落库至 MySQL（`orders` / `refunds` 表，见 [order-persistence.md](./order-persistence.md)），不再依赖文件存储。
- 生产环境下一步：接入真实微信/支付宝/银联支付渠道（校验签名、证书），替换当前的模拟回调。
