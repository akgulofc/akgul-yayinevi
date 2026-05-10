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

  const { messages = [], data = {} } = body;

  const storeContext = `
Kitaplar: ${JSON.stringify((data.books || []).slice(0, 40))}
Siparişler: ${JSON.stringify((data.orders || []).slice(0, 20))}
Blog yazıları: ${JSON.stringify((data.blogPosts || []).slice(0, 20))}
Yazarlar: ${JSON.stringify((data.authors || []).slice(0, 20))}
Kullanıcılar: ${JSON.stringify((data.users || []).slice(0, 20))}`;

  const systemPrompt = `Sen Akgül Yayınevi'nin admin asistanısın. Kitap, sipariş, blog ve yazar yönetimi konusunda yardım edersin.

Eğer kullanıcı bir işlem yapmamı isterse (kitap ekle, güncelle, sil; sipariş durumu değiştir; blog yazısı ekle; sayfaya git), yanıtını MUTLAKA şu JSON formatında ver:
{"reply":"Kullanıcıya mesaj","actions":[{"tool":"araç_adı","params":{...}}]}

Kullanılabilir araçlar:
- add_book: {title, author, price, cat, badge, desc}
- update_book: {id, title?, author?, price?, desc?, badge?}
- delete_book: {id}
- update_order_status: {id, status}
- bulk_update_orders: {from_status, to_status}
- add_blog_post: {title, author, cat, status, content}
- navigate_to: {page} (dashboard/books/orders/users/blog/reviews/authors/settings)

Eğer sadece bilgi veriyorsan düz metin yanıt ver. Türkçe konuş, kısa ve net ol.

Mevcut veriler:${storeContext}`;

  const groqRes = await fetch('https://api.groq.com/openai/v1/chat/completions', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': 'Bearer ' + apiKey,
    },
    body: JSON.stringify({
      model: 'llama-3.3-70b-versatile',
      messages: [
        { role: 'system', content: systemPrompt },
        ...messages.slice(-10),
      ],
      temperature: 0.4,
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

  const content = raw.choices[0].message.content.trim();

  let reply = content;
  let actions = [];

  try {
    const jsonMatch = content.match(/\{[\s\S]*\}/);
    if (jsonMatch) {
      const parsed = JSON.parse(jsonMatch[0]);
      if (parsed.reply) reply = parsed.reply;
      if (Array.isArray(parsed.actions)) actions = parsed.actions;
    }
  } catch {
    // düz metin yanıt, actions boş kalır
  }

  return {
    statusCode: 200,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ reply, actions }),
  };
};
