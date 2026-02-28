import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Insumo, InsumoStats } from '../../core/models/insumo.model';
import { InsumosService } from '../../core/services/insumos.service';

@Component({
  selector: 'app-insumos-page',
  imports: [CommonModule, FormsModule],
  templateUrl: './insumos-page.component.html',
  styleUrl: './insumos-page.component.scss'
})
export class InsumosPageComponent implements OnInit {
  items: Insumo[] = [];
  stats: InsumoStats = {
    expirados: 0,
    vencendo7dias: 0,
    vencendo30dias: 0
  };

  startDate = '';
  endDate = '';

  isLoading = false;
  saving = false;
  errorMessage = '';
  successMessage = '';

  editingId: number | null = null;

  form: Insumo = this.newForm();

  constructor(private readonly insumosService: InsumosService) {}

  ngOnInit(): void {
    this.load();
  }

  newForm(): Insumo {
    return {
      data_contagem: null,
      unidade: 'UN',
      nome: '',
      posicao: '',
      lote: null,
      quantidade: 0,
      data_entrada: '',
      validade: '',
      observacoes: null
    };
  }

  load(): void {
    this.isLoading = true;
    this.errorMessage = '';

    this.insumosService
      .list({
        startDate: this.startDate || undefined,
        endDate: this.endDate || undefined
      })
      .subscribe({
        next: (response) => {
          this.items = response.data;
          this.stats = response.stats;
          this.isLoading = false;
        },
        error: (error) => {
          this.errorMessage = error?.error?.detail || 'Erro ao carregar insumos.';
          this.isLoading = false;
        }
      });
  }

  applyFilters(): void {
    this.load();
  }

  clearFilters(): void {
    this.startDate = '';
    this.endDate = '';
    this.load();
  }

  edit(item: Insumo): void {
    this.editingId = item.id ?? null;
    this.form = {
      data_contagem: item.data_contagem,
      unidade: item.unidade,
      nome: item.nome,
      posicao: item.posicao,
      lote: item.lote,
      quantidade: item.quantidade,
      data_entrada: item.data_entrada,
      validade: item.validade,
      observacoes: item.observacoes
    };
    this.successMessage = '';
    this.errorMessage = '';
  }

  resetForm(): void {
    this.editingId = null;
    this.form = this.newForm();
  }

  save(): void {
    this.saving = true;
    this.errorMessage = '';
    this.successMessage = '';

    const payload: Insumo = {
      data_contagem: this.form.data_contagem || null,
      unidade: this.form.unidade,
      nome: this.form.nome,
      posicao: this.form.posicao,
      lote: this.form.lote || null,
      quantidade: Number(this.form.quantidade),
      data_entrada: this.form.data_entrada,
      validade: this.form.validade,
      observacoes: this.form.observacoes || null
    };

    const request = this.editingId
      ? this.insumosService.update(this.editingId, payload)
      : this.insumosService.create(payload);

    request.subscribe({
      next: () => {
        this.saving = false;
        this.successMessage = this.editingId
          ? 'Insumo atualizado com sucesso.'
          : 'Insumo criado com sucesso.';
        this.resetForm();
        this.load();
      },
      error: (error) => {
        this.saving = false;
        const backendDetails = error?.error?.details;
        if (Array.isArray(backendDetails) && backendDetails.length > 0) {
          this.errorMessage = backendDetails.join(' ');
          return;
        }

        this.errorMessage = error?.error?.detail || 'Erro ao salvar insumo.';
      }
    });
  }

  remove(item: Insumo): void {
    if (!item.id) {
      return;
    }

    const confirmed = window.confirm(`Deseja excluir o item ${item.nome}?`);
    if (!confirmed) {
      return;
    }

    this.errorMessage = '';
    this.successMessage = '';

    this.insumosService.remove(item.id).subscribe({
      next: () => {
        this.successMessage = 'Insumo excluído com sucesso.';
        this.load();
      },
      error: (error) => {
        this.errorMessage = error?.error?.detail || 'Erro ao excluir insumo.';
      }
    });
  }
}
