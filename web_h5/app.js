async function createOrder() {
  const response = await fetch('https://localhost:8443/api/order/create', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ amount: 68.88, pay_method: 'wechat' })
  });
  const result = await response.json();
  document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
}

document.getElementById('payBtn').addEventListener('click', createOrder);
