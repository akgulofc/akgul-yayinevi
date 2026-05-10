exports.handler = async (event) => {
  if (event.httpMethod !== 'POST') {
    return { statusCode: 405, body: 'Method Not Allowed' };
  }

  const apiKey = process.env.GROQ_API_KEY;
  if (!apiKey) {
    return { statusCode: 500, body: JSON.stringify({ error: 'API key eksik' }) };
  }

  let body;
  try {
    body = JSON.parse(event.body);
  } catch {
    return { statusCode: 400, body: JSON.stringify({ error: 'Geçersiz istek' }) };
  }

  const { mood, books } = body;

  const prompt = `Kullanıcının ruh hali: "${mood}". Aşağıdaki kitap kataloğundan bu ruh haline en uygun 4-6 kitabı seç. Her kitap için kısa ve samimi bir Türkçe neden yaz (1-2 cümle). Yanıtı SADECE geçerli JSON olarak ver:\n{"recommendations":[{"id":KITAP_ID,"reason":"neden"}]}\n\nKatalog:\n${JSON.stringify(books)}`;

  const groqRes = await fetch('https://api.groq.com/openai/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + apiKey,
    },
    body: JSON.stringify({
      model: 'llama-3.3-70b-versatile',
      messages: [
        { role: 'system', content: 'Sen Akgül Yayınevi\'nin kitap öneri asistanısın. Sadece JSON formatında yanıt ver.' },
        { role: 'user', content: prompt },
      ],
      response_format: { type: 'json_object' },
      temperature: 0.7,
      max_tokens: 1024,
    }),
  });

  const raw = await groqRes.json();

  if (!groqRes.ok) {
    return {
      statusCode: 502,
      body: JSON.stringify({ error: raw.error?.message || 'Groq hatası' }),
    };
  }

  return {
    statusCode: 200,
    headers: { 'Content-Type': 'application/json' },
    body: raw.choices[0].message.content,
  };
};
