import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
const php = new PHP(await loadNodeRuntime('8.3', { emscriptenOptions: { processId: 15 } }));
useHostFilesystem(php);
const out = await php.runStream({ code: `<?php
$base = '/home/user/autoblog-Local/backlink-maker/';
$files = ['index.php','includes/database.php','includes/publishers.php','includes/maker.php','includes/content_engine.php','includes/helpers.php','includes/api_profiles.php','cron/daily.php','cron/backlink-30min.php'];
foreach ($files as $f) {
  $src = file_get_contents($base . $f);
  try { token_get_all($src, TOKEN_PARSE); echo "OK   $f\\n"; }
  catch (Throwable $e) { echo "FAIL $f -> " . $e->getMessage() . "\\n"; }
}
`});
console.log(await out.stdoutText);
process.exit(0);
