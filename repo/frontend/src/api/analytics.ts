import apiClient from './client';
import type { ApiResponse } from './types';

/* ------------------------------------------------------------------ */
/*  Analytics interfaces                                               */
/* ------------------------------------------------------------------ */

export interface KpiSummary {
  total_sales: number;
  total_orders: number;
  content_count: number;
  export_count: number;
  retention_count: number;
  sensitive_access_count: number;
}

export interface SalesData {
  product?: string;
  category?: string;
  region?: string;
  channel?: string;
  date?: string;
  gross_sales: number;
  net_sales: number;
  quantity: number;
  order_count: number;
}

export interface SalesTrend {
  date: string;
  gross_sales: number;
  net_sales: number;
  quantity: number;
}

export interface ContentVolume {
  content_type: string;
  count: number;
  store_id?: string;
}

export interface SalesFilterParams {
  region?: string;
  channel?: string;
  date_from?: string;
  date_to?: string;
  granularity?: 'day' | 'week' | 'month';
  page?: number;
  per_page?: number;
}

/* ------------------------------------------------------------------ */
/*  API functions                                                      */
/* ------------------------------------------------------------------ */

export async function getKpiSummary(): Promise<ApiResponse<KpiSummary>> {
  const res = await apiClient.get<ApiResponse<KpiSummary>>('/analytics/kpi-summary');
  return res.data;
}

export async function getSalesByDimensions(
  params?: SalesFilterParams,
): Promise<ApiResponse<SalesData[]>> {
  const res = await apiClient.get<ApiResponse<SalesData[]>>('/analytics/sales', {
    params,
  });
  return res.data;
}

export async function getSalesTrends(
  params?: SalesFilterParams,
): Promise<ApiResponse<SalesTrend[]>> {
  const res = await apiClient.get<ApiResponse<SalesTrend[]>>('/analytics/sales/trends', {
    params,
  });
  return res.data;
}

export async function getContentVolume(): Promise<ApiResponse<ContentVolume[]>> {
  const res = await apiClient.get<ApiResponse<ContentVolume[]>>(
    '/analytics/content-volume',
  );
  return res.data;
}
