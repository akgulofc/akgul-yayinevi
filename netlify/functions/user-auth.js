const CORS = {
  'Content-Type': 'application/json',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'Content-Type',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};

exports.handler = async (event) => {
  if (event.httpMethod === 'OPTIONS') return { statusCode: 204, headers: CORS, body: '' };
  if (event.httpMethod !== 'POST') return { statusCode: 405, headers: CORS, body: JSON.stringify({ error: 'Method Not Allowed' }) };

  const token = process.env.GITHUB_TOKEN;
  const repo = process.env.PRIVATE_REPO_NAME || 'akgul-data';
  const owner = 'akgulofc';
  if (!token) return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'Sunucu yapılandırma hatası' }) };

  let body;
  try { body = JSON.parse(event.body); } catch {
    return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'Geçersiz istek' }) };
  }

  const { action } = body;
  const apiUrl = `https://api.github.com/repos/${owner}/${repo}/contents/users.json`;
  const ghHeaders = { 'Authorization': `token ${token}`, 'Accept': 'application/vnd.github.v3+json', 'Content-Type': 'application/json', 'User-Agent': 'akgul-admin-bot' };

  // Kullanıcı listesini oku
  let users = [];
  let fileSha;
  try {
    const r = await fetch(apiUrl, { headers: ghHeaders });
    if (r.ok) {
      const data = await r.json();
      fileSha = data.sha;
      users = JSON.parse(Buffer.from(data.content, 'base64').toString('utf8'));
    }
  } catch {
    return { statusCode: 503, headers: CORS, body: JSON.stringify({ error: 'Sunucu verisi okunamadı' }) };
  }

  if (action === 'login') {
    const { email, pass } = body;
    if (!email || !pass) return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'E-posta ve şifre gerekli' }) };
    const found = users.find(u => u.email.toLowerCase() === email.toLowerCase() && u.pass === pass);
    if (!found) return { statusCode: 401, headers: CORS, body: JSON.stringify({ error: 'E-posta veya şifre hatalı' }) };
    const { pass: _p, ...safeUser } = found;
    return { statusCode: 200, headers: CORS, body: JSON.stringify({ user: safeUser }) };
  }

  if (action === 'register') {
    const { name, email, pass, role, joined } = body;
    if (!name || !email || !pass) return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'Ad, e-posta ve şifre gerekli' }) };
    if (users.find(u => u.email.toLowerCase() === email.toLowerCase())) {
      return { statusCode: 409, headers: CORS, body: JSON.stringify({ error: 'Bu e-posta zaten kayıtlı' }) };
    }
    const newUser = { name, email, pass, role: role || 'okur', joined: joined || new Date().toISOString().split('T')[0] };
    users.push(newUser);

    // Kaydet
    const content = Buffer.from(JSON.stringify(users, null, 2)).toString('base64');
    const putBody = { message: `Yeni üye: ${name}`, content, committer: { name: 'Akgül System', email: 'akgulyayinevi@gmail.com' } };
    if (fileSha) putBody.sha = fileSha;
    try {
      await fetch(apiUrl, { method: 'PUT', headers: ghHeaders, body: JSON.stringify(putBody) });
    } catch {
      return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'Kayıt sunucuya yazılamadı' }) };
    }
    const { pass: _p, ...safeUser } = newUser;
    return { statusCode: 200, headers: CORS, body: JSON.stringify({ user: safeUser }) };
  }

  return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'Geçersiz action' }) };
};
