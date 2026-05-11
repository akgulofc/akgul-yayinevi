const CORS = {
  'Content-Type': 'application/json',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'Content-Type, x-admin-secret',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};

exports.handler = async (event) => {
  if (event.httpMethod === 'OPTIONS') {
    return { statusCode: 204, headers: CORS, body: '' };
  }
  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, headers: CORS, body: JSON.stringify({ error: 'Method Not Allowed' }) };
  }

  const secret = event.headers['x-admin-secret'];
  const expected = process.env.ADMIN_SECRET;
  if (!expected || !secret || secret !== expected) {
    return { statusCode: 401, headers: CORS, body: JSON.stringify({ error: 'Yetkisiz erişim' }) };
  }

  const token = process.env.GITHUB_TOKEN;
  if (!token) {
    return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'GITHUB_TOKEN tanımlı değil' }) };
  }

  let books;
  try {
    ({ books } = JSON.parse(event.body));
    if (!Array.isArray(books)) throw new Error('books array değil');
  } catch {
    return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'Geçersiz istek gövdesi' }) };
  }

  const owner = 'akgulofc';
  const repo = 'akgul-yayinevi';
  const filePath = 'data/books.json';
  const apiUrl = `https://api.github.com/repos/${owner}/${repo}/contents/${filePath}`;
  const ghHeaders = {
    'Authorization': `token ${token}`,
    'Accept': 'application/vnd.github.v3+json',
    'Content-Type': 'application/json',
    'User-Agent': 'akgul-admin-bot',
  };

  // Mevcut dosyanın SHA değerini al (güncelleme için gerekli)
  let sha;
  try {
    const getRes = await fetch(apiUrl, { headers: ghHeaders });
    if (getRes.ok) {
      const fileData = await getRes.json();
      sha = fileData.sha;
    }
  } catch {
    return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'GitHub dosyası okunamadı' }) };
  }

  // Dosyayı güncelle
  const content = Buffer.from(JSON.stringify(books, null, 2)).toString('base64');
  const putBody = {
    message: `Admin: kitap listesi güncellendi`,
    content,
    committer: { name: 'Akgül Admin', email: 'akgulyayinevi@gmail.com' },
  };
  if (sha) putBody.sha = sha;

  try {
    const putRes = await fetch(apiUrl, {
      method: 'PUT',
      headers: ghHeaders,
      body: JSON.stringify(putBody),
    });
    if (!putRes.ok) {
      const errText = await putRes.text();
      return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: `GitHub API hatası: ${errText}` }) };
    }
  } catch {
    return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'GitHub API isteği başarısız' }) };
  }

  return { statusCode: 200, headers: CORS, body: JSON.stringify({ success: true }) };
};
