/**
 * AutoBacklink — live preview server (php-wasm). DEV-ONLY tool, never deployed.
 * Emulates Apache for the backlink-maker folder:
 *  - static files served directly
 *  - everything else rewritten to index.php (same as .htaccess)
 */
import http from 'node:http';
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';

const APP = '/home/user/autoblog-Local/backlink-maker';
const PORT = 8085;

console.log('[preview] loading PHP 8.3 (WASM)…');
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 1 } }));
useHostFilesystem(php); // mount the real host filesystem into the WASM FS

const handler = new PHPRequestHandler({
  php,
  documentRoot: APP,
  absoluteUrl: 'http://preview.local', // populates HTTP_HOST etc.
  cookieStore: false, // auth is DB-token based; browsers keep their own cookies
  rewriteRules: [
    // same behavior as .htaccess: all non-static → index.php
    { match: /.*/, replacement: '/index.php' },
  ],
});

console.log('[preview] PHP ready. Handler initialized.');

// Serialize requests (single WASM instance is not concurrency-safe).
let queue = Promise.resolve();
function enqueue(fn) {
  const run = queue.then(fn, fn);
  queue = run.catch(() => {});
  return run;
}

const server = http.createServer((req, res) => {
  const chunks = [];
  req.on('data', (c) => chunks.push(c));
  req.on('end', () => {
    enqueue(async () => {
      const body = Buffer.concat(chunks);
      const path = (req.url || '/').split('?')[0];
      const headers = { ...req.headers };
      headers['host'] = 'preview.local';
      try {
        const out = await handler.request({
          method: req.method,
          url: 'http://preview.local' + path,
          headers,
          body: body.length ? new Uint8Array(body) : undefined,
        });
        const status = out.httpStatusCode ?? out.statusCode ?? 200;
        res.writeHead(status, out.headers ?? {});
        if (out.bytes) res.end(out.bytes);
        else if (typeof out.text === 'string') res.end(out.text);
        else if (out.stdout) res.end(await out.stdout);
        else res.end();
      } catch (e) {
        console.error('[preview] request error:', e.message);
        if (!res.headersSent) res.writeHead(500, { 'Content-Type': 'text/plain' });
        res.end('Preview error: ' + e.message);
      }
    });
  });
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[preview] AutoBacklink preview listening on 0.0.0.0:${PORT}`);
});
