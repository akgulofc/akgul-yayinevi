const CORS = {
  'Content-Type': 'application/json',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'Content-Type, x-admin-secret',
  'Access-Control-Allow-Methods': 'POST, OPTIONS',
};

exports.handler = async (event) => {
  if (event.httpMethod === 'OPTIONS') return { statusCode: 204, headers: CORS, body: '' };
  if (event.httpMethod !== 'POST') return { statusCode: 405, headers: CORS, body: JSON.stringify({ error: 'Method Not Allowed' }) };

  const secret = event.headers['x-admin-secret'];
  if (!secret || secret !== process.env.ADMIN_SECRET) {
    return { statusCode: 401, headers: CORS, body: JSON.stringify({ error: 'Yetkisiz erişim' }) };
  }

  const token = process.env.GITHUB_TOKEN;
  const repo = process.env.PRIVATE_REPO_NAME || 'akgul-data';
  const owner = 'akgulofc';
  if (!token) return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'GITHUB_TOKEN eksik' }) };

  let authors;
  try {
    ({ authors } = JSON.parse(event.body));
    if (!Array.isArray(authors)) throw new Error();
  } catch {
    return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'Geçersiz istek' }) };
  }

  const result = await writeToPrivateRepo(owner, repo, 'authors.json', authors, 'Admin: yazarlar güncellendi', token);
  return result.ok
    ? { statusCode: 200, headers: CORS, body: JSON.stringify({ success: true }) }
    : { statusCode: 500, headers: CORS, body: JSON.stringify({ error: result.error }) };
};

async function writeToPrivateRepo(owner, repo, path, data, message, token) {
  const apiUrl = `https://api.github.com/repos/${owner}/${repo}/contents/${path}`;
  const headers = {
    'Authorization': `token ${token}`,
    'Accept': 'application/vnd.github.v3+json',
    'Content-Type': 'application/json',
    'User-Agent': 'akgul-admin-bot',
  };
  let sha;
  try {
    const r = await fetch(apiUrl, { headers });
    if (r.ok) sha = (await r.json()).sha;
  } catch { return { ok: false, error: 'Dosya SHA alınamadı' }; }

  const content = Buffer.from(JSON.stringify(data, null, 2)).toString('base64');
  const body = { message, content, committer: { name: 'Akgül Admin', email: 'akgulyayinevi@gmail.com' } };
  if (sha) body.sha = sha;

  try {
    const r = await fetch(apiUrl, { method: 'PUT', headers, body: JSON.stringify(body) });
    if (!r.ok) return { ok: false, error: await r.text() };
    return { ok: true };
  } catch { return { ok: false, error: 'GitHub API hatası' }; }
}
