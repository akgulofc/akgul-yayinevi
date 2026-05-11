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

  let email, password;
  try {
    ({ email, password } = JSON.parse(event.body));
    if (!email || !password) throw new Error();
  } catch {
    return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'E-posta ve şifre gerekli' }) };
  }

  // Özel repodan yazar listesini oku
  let authors = [];
  let fileSha;
  try {
    const apiUrl = `https://api.github.com/repos/${owner}/${repo}/contents/authors.json`;
    const headers = { 'Authorization': `token ${token}`, 'Accept': 'application/vnd.github.v3+json', 'User-Agent': 'akgul-admin-bot' };
    const r = await fetch(apiUrl, { headers });
    if (!r.ok) return { statusCode: 503, headers: CORS, body: JSON.stringify({ error: 'Sunucu verisi okunamadı' }) };
    const data = await r.json();
    fileSha = data.sha;
    authors = JSON.parse(Buffer.from(data.content, 'base64').toString('utf8'));
  } catch {
    return { statusCode: 503, headers: CORS, body: JSON.stringify({ error: 'Sunucu verisi okunamadı' }) };
  }

  // Kimlik doğrula
  const found = authors.find(a =>
    a.membership &&
    a.membership.email &&
    a.membership.email.toLowerCase() === email.toLowerCase() &&
    a.membership.password === password
  );

  if (!found) return { statusCode: 401, headers: CORS, body: JSON.stringify({ error: 'E-posta veya şifre hatalı' }) };
  if (found.membership.status === 'suspended') return { statusCode: 403, headers: CORS, body: JSON.stringify({ error: 'Üyeliğiniz askıya alınmıştır. Yayınevi ile iletişime geçin.' }) };

  // Son giriş tarihini güncelle
  found.membership.lastLogin = new Date().toISOString().split('T')[0];
  try {
    const apiUrl = `https://api.github.com/repos/${owner}/${repo}/contents/authors.json`;
    const headers = { 'Authorization': `token ${token}`, 'Accept': 'application/vnd.github.v3+json', 'Content-Type': 'application/json', 'User-Agent': 'akgul-admin-bot' };
    await fetch(apiUrl, {
      method: 'PUT', headers,
      body: JSON.stringify({
        message: `Yazar girişi: ${found.name}`,
        content: Buffer.from(JSON.stringify(authors, null, 2)).toString('base64'),
        sha: fileSha,
        committer: { name: 'Akgül System', email: 'akgulyayinevi@gmail.com' },
      }),
    });
  } catch { /* son giriş güncelleme kritik değil, sessizce geç */ }

  // Şifreyi çıkararak döndür
  const { password: _pwd, ...safeMembership } = found.membership;
  return {
    statusCode: 200,
    headers: CORS,
    body: JSON.stringify({ author: { ...found, membership: safeMembership } }),
  };
};
