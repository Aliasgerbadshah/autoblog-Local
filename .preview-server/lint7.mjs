import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 15 } }));
useHostFilesystem(php);
const out = await php.runStream({ code: `<?php
foreach (['index.php','includes/publishers.php'] as $f) {
  $src = file_get_contents('/home/user/autoblog-Local/backlink-maker/' . $f);
  try { token_get_all($src, TOKEN_PARSE); echo "OK   $f\\n"; }
  catch (Throwable $e) { echo "FAIL $f -> " . $e->getMessage() . "\\n"; }
}
`});
console.log(await out.stdoutText);
process.exit(0);
