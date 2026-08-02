const API_BASE = window.__API_BASE__ || 'https://api.kmgrnet.com';
const apiUrl = (path) => `${API_BASE}${path}`;

async function createOrder() {
  const amount = document.getElementById('amount').value || '99.99';
  const payMethod = document.getElementById('payMethod').value || 'wechat';

  const response = await fetch(apiUrl('/api/order/create'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ amount: Number(amount), pay_method: payMethod })
  });

  const result = await response.json();
  const output = document.getElementById('result');
  output.innerHTML = '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
}

document.getElementById('payBtn').addEventListener('click', createOrder);
