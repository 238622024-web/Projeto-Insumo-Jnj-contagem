import cors from 'cors';
import dotenv from 'dotenv';
import express from 'express';
import insumosRouter from './routes/insumos.js';

dotenv.config();

const app = express();

app.use(cors());
app.use(express.json());

app.get('/health', (_req, res) => {
  res.json({ ok: true });
});

app.use('/api/insumos', insumosRouter);

const port = Number(process.env.PORT || 3000);

app.listen(port, () => {
  console.log(`API Node rodando em http://localhost:${port}`);
});
