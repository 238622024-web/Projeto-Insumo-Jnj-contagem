import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { Insumo, InsumosListResponse } from '../models/insumo.model';

@Injectable({
  providedIn: 'root'
})
export class InsumosService {
  private readonly baseUrl = '/api/insumos';

  constructor(private readonly http: HttpClient) {}

  list(filters?: { startDate?: string; endDate?: string }): Observable<InsumosListResponse> {
    let params = new HttpParams();

    if (filters?.startDate) {
      params = params.set('start_date', filters.startDate);
    }

    if (filters?.endDate) {
      params = params.set('end_date', filters.endDate);
    }

    return this.http.get<InsumosListResponse>(this.baseUrl, { params });
  }

  create(payload: Insumo): Observable<Insumo> {
    return this.http.post<Insumo>(this.baseUrl, payload);
  }

  update(id: number, payload: Insumo): Observable<Insumo> {
    return this.http.put<Insumo>(`${this.baseUrl}/${id}`, payload);
  }

  remove(id: number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }
}
