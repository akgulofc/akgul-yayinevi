const CORS = {
  'Content-Type': 'application/json',
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'Content-Type',
};

exports.handler = async (event) => {
  if (event.httpMethod === 'OPTIONS') {
    return { statusCode: 204, headers: CORS, body: '' };
  }
  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, headers: CORS, body: JSON.stringify({ error: 'Method Not Allowed' }) };
  }

  const apiKey = process.env.GROQ_API_KEY;
  if (!apiKey) {
    return { statusCode: 500, headers: CORS, body: JSON.stringify({ error: 'GROQ_API_KEY eksik' }) };
  }

  let body;
  try {
    body = JSON.parse(event.body);
  } catch {
    return { statusCode: 400, headers: CORS, body: JSON.stringify({ error: 'Geçersiz istek' }) };
  }

  const { messages = [], data = {} } = body;

  const storeContext = data.books
    ? `Mevcut kitaplar (${data.books.length} adet): ${JSON.stringify(data.books.slice(0, 30))}
Yazarlar: ${JSON.stringify((data.authors || []).slice(0, 10))}
Blog yazıları: ${JSON.stringify((data.blog || []).slice(0, 5))}`
    : '';

  const systemPrompt = `Sen Akgül Yayınevi'nin samimi ve bilgili kitap asistanısın. Müşterilere kitap önerisi yapar, yayınevi hakkında bilgi verirsin. Kısa ve sıcak yanıtlar ver, Türkçe konuş.\n\n${storeContext}`;

  const groqRes = await fetch('https://api.groq.com/openai/v1/chat/completions', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + apiKey },
    body: JSON.stringify({
      model: 'llama-3.3-70b-versatile',
      messages: [{ role: 'system', content: systemPrompt }, ...messages.slice(-10)],
      temperature: 0.7,
      max_tokens: 512,
    }),
  });

  const raw = await groqRes.json();

  if (!groqRes.ok) {
    return { statusCode: 502, headers: CORS, body: JSON.stringify({ error: raw.error?.message || 'Groq hatası' }) };
  }

  return { statusCode: 200, headers: CORS, body: JSON.stringify({ reply: raw.choices[0].message.content }) };
};
