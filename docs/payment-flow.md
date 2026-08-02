# 支付流程说明

## 方案 B：API 异步交互

1. 前端在 PC/H5/小程序中提交订单创建请求。
2. 后端调用 Likeshop 生成支付参数或收银台地址。
3. 前端根据终端类型展示支付二维码、跳转收银台或唤起微信/支付宝 App。
4. 支付完成后由第三方支付机构回调 Notify 接口。
5. 前端轮询订单状态，确认支付结果后展示成功状态。

## 推荐接口

- POST /api/order/create
- GET /api/order/status
- POST /api/payment/notify/wechat
- POST /api/payment/notify/alipay
- POST /api/payment/notify/unionpay
