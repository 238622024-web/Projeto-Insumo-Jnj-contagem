import { Router } from 'express';
import dayjs from 'dayjs';
import { pool } from '../db.js';

const router = Router();

function getErrorDetail(error) {
  return (
    error?.sqlMessage ||
    error?.message ||
    error?.code ||
    'Erro desconhecido'
  );
}

function diasPara(data) {
  if (!data) {
    return null;
  }

  const hoje = dayjs().startOf('day');
  const dataValidade = dayjs(data).startOf('day');

  if (!dataValidade.isValid()) {
    return null;
  }

  return dataValidade.diff(hoje, 'day');
}

function isValidDate(value) {
  if (!value || typeof value !== 'string') {
    return false;
  }

  const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
  if (!dateRegex.test(value)) {
    return false;
  }

  return dayjs(value).isValid();
}

function normalizePayload(body) {
  return {
    data_contagem: body.data_contagem ? String(body.data_contagem).trim() : null,
    unidade: body.unidade ? String(body.unidade).trim() : 'UN',
    nome: body.nome ? String(body.nome).trim() : '',
    posicao: body.posicao ? String(body.posicao).trim() : '',
    lote: body.lote ? String(body.lote).trim() : null,
    quantidade: Number(body.quantidade ?? 0),
    data_entrada: body.data_entrada ? String(body.data_entrada).trim() : '',
    validade: body.validade ? String(body.validade).trim() : '',
    observacoes: body.observacoes ? String(body.observacoes).trim() : null
  };
}

function validatePayload(payload) {
  const errors = [];

  if (!payload.nome) {
    errors.push('Campo nome é obrigatório.');
  }

  if (!payload.posicao) {
    errors.push('Campo posicao é obrigatório.');
  }

  if (!isValidDate(payload.data_entrada)) {
    errors.push('Campo data_entrada deve estar no formato YYYY-MM-DD.');
  }

  if (!isValidDate(payload.validade)) {
    errors.push('Campo validade deve estar no formato YYYY-MM-DD.');
  }

  if (payload.data_contagem && !isValidDate(payload.data_contagem)) {
    errors.push('Campo data_contagem deve estar no formato YYYY-MM-DD.');
  }

  if (!Number.isInteger(payload.quantidade) || payload.quantidade < 0) {
    errors.push('Campo quantidade deve ser um inteiro maior ou igual a zero.');
  }

  return errors;
}

router.get('/', async (req, res) => {
  try {
    const startDate = String(req.query.start_date || '').trim();
    const endDate = String(req.query.end_date || '').trim();

    const where = [];
    const params = [];

    if (startDate) {
      where.push('data_entrada >= ?');
      params.push(startDate);
    }

    if (endDate) {
      where.push('data_entrada <= ?');
      params.push(endDate);
    }

    let sql = 'SELECT * FROM insumos_jnj';
    if (where.length > 0) {
      sql += ` WHERE ${where.join(' AND ')}`;
    }
    sql += ' ORDER BY id DESC';

    const [rows] = await pool.execute(sql, params);

    let expirados = 0;
    let vencendo7dias = 0;
    let vencendo30dias = 0;

    for (const item of rows) {
      const dias = diasPara(item.validade);

      if (dias === null) {
        continue;
      }

      if (dias < 0) {
        expirados += 1;
      } else if (dias <= 7) {
        vencendo7dias += 1;
      } else if (dias <= 30) {
        vencendo30dias += 1;
      }
    }

    res.json({
      total: rows.length,
      stats: {
        expirados,
        vencendo7dias,
        vencendo30dias
      },
      data: rows
    });
  } catch (error) {
    console.error('GET /api/insumos error:', error);
    res.status(500).json({
      error: 'Erro ao listar insumos',
      detail: getErrorDetail(error)
    });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const id = Number(req.params.id);

    if (!Number.isInteger(id) || id <= 0) {
      return res.status(400).json({ error: 'ID inválido.' });
    }

    const [rows] = await pool.execute('SELECT * FROM insumos_jnj WHERE id = ?', [id]);

    if (rows.length === 0) {
      return res.status(404).json({ error: 'Insumo não encontrado.' });
    }

    return res.json(rows[0]);
  } catch (error) {
    console.error('GET /api/insumos/:id error:', error);
    return res.status(500).json({
      error: 'Erro ao buscar insumo',
      detail: getErrorDetail(error)
    });
  }
});

router.post('/', async (req, res) => {
  try {
    const payload = normalizePayload(req.body);
    const errors = validatePayload(payload);

    if (errors.length > 0) {
      return res.status(400).json({
        error: 'Payload inválido',
        details: errors
      });
    }

    const sql = `
      INSERT INTO insumos_jnj
      (data_contagem, unidade, nome, posicao, lote, quantidade, data_entrada, validade, observacoes)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `;

    const [result] = await pool.execute(sql, [
      payload.data_contagem,
      payload.unidade,
      payload.nome,
      payload.posicao,
      payload.lote,
      payload.quantidade,
      payload.data_entrada,
      payload.validade,
      payload.observacoes
    ]);

    const [created] = await pool.execute('SELECT * FROM insumos_jnj WHERE id = ?', [result.insertId]);

    return res.status(201).json(created[0]);
  } catch (error) {
    console.error('POST /api/insumos error:', error);
    return res.status(500).json({
      error: 'Erro ao criar insumo',
      detail: getErrorDetail(error)
    });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const id = Number(req.params.id);

    if (!Number.isInteger(id) || id <= 0) {
      return res.status(400).json({ error: 'ID inválido.' });
    }

    const payload = normalizePayload(req.body);
    const errors = validatePayload(payload);

    if (errors.length > 0) {
      return res.status(400).json({
        error: 'Payload inválido',
        details: errors
      });
    }

    const updateSql = `
      UPDATE insumos_jnj
      SET
        data_contagem = ?,
        unidade = ?,
        nome = ?,
        posicao = ?,
        lote = ?,
        quantidade = ?,
        data_entrada = ?,
        validade = ?,
        observacoes = ?
      WHERE id = ?
    `;

    const [result] = await pool.execute(updateSql, [
      payload.data_contagem,
      payload.unidade,
      payload.nome,
      payload.posicao,
      payload.lote,
      payload.quantidade,
      payload.data_entrada,
      payload.validade,
      payload.observacoes,
      id
    ]);

    if (result.affectedRows === 0) {
      return res.status(404).json({ error: 'Insumo não encontrado.' });
    }

    const [updated] = await pool.execute('SELECT * FROM insumos_jnj WHERE id = ?', [id]);

    return res.json(updated[0]);
  } catch (error) {
    console.error('PUT /api/insumos/:id error:', error);
    return res.status(500).json({
      error: 'Erro ao atualizar insumo',
      detail: getErrorDetail(error)
    });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    const id = Number(req.params.id);

    if (!Number.isInteger(id) || id <= 0) {
      return res.status(400).json({ error: 'ID inválido.' });
    }

    const [result] = await pool.execute('DELETE FROM insumos_jnj WHERE id = ?', [id]);

    if (result.affectedRows === 0) {
      return res.status(404).json({ error: 'Insumo não encontrado.' });
    }

    return res.status(204).send();
  } catch (error) {
    console.error('DELETE /api/insumos/:id error:', error);
    return res.status(500).json({
      error: 'Erro ao excluir insumo',
      detail: getErrorDetail(error)
    });
  }
});

export default router;
