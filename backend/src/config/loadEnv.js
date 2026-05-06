import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';

let envLoaded = false;

export function loadEnv() {
  if (envLoaded) {
    return;
  }

  const backendRoot = fileURLToPath(new URL('../../', import.meta.url));
  const envFiles = [
    path.join(backendRoot, '.env.laragon'),
    path.join(backendRoot, '.env')
  ];

  for (const envFile of envFiles) {
    if (fs.existsSync(envFile)) {
      dotenv.config({ path: envFile });
    }
  }

  envLoaded = true;
}