export interface Insumo {
  id?: number;
  data_contagem: string | null;
  unidade: string;
  nome: string;
  posicao: string;
  lote: string | null;
  quantidade: number;
  data_entrada: string;
  validade: string;
  observacoes: string | null;
}

export interface InsumoStats {
  expirados: number;
  vencendo7dias: number;
  vencendo30dias: number;
}

export interface InsumosListResponse {
  total: number;
  stats: InsumoStats;
  data: Insumo[];
}
