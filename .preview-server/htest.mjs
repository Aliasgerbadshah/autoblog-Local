// Probe the real Hashnode GraphQL endpoint to see what it actually returns.
async function probe(label, url, headers, body) {
  console.log('\n========== ' + label + ' ==========');
  console.log('POST', url);
  try {
    const r = await fetch(url, { method: 'POST', headers, body });
    const text = await r.text();
    console.log('HTTP', r.status, '| content-type:', r.headers.get('content-type'));
    console.log('first 400 chars:', text.slice(0, 400).replace(/\n/g, ' '));
    // Is it JSON?
    try { JSON.parse(text); console.log('>>> RESPONSE IS VALID JSON'); }
    catch { console.log('>>> RESPONSE IS NOT JSON (HTML/other)'); }
  } catch (e) {
    console.log('FETCH ERROR:', e.message);
  }
}

// 1) Unauthenticated simple query (docs say public queries need no auth)
await probe('1. unauth public query', 'https://gql.hashnode.com',
  { 'Content-Type': 'application/json', 'Accept': 'application/json' },
  JSON.stringify({ query: 'query { me { id username } }' }));

// 2) publishPost mutation with a FAKE token (to see auth error shape)
await probe('2. publishPost with fake token', 'https://gql.hashnode.com',
  { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'FAKE-TOKEN-00000000' },
  JSON.stringify({
    query: 'mutation ($input: PublishPostInput!) { publishPost(input: $input) { ok post { url } } }',
    variables: { input: { publicationId: '000000000000000000000000', title: 'diag test', contentMarkdown: 'hello' } }
  }));
