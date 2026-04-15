import apiClient from './client';
import type { ApiResponse, DeliveryZone, DeliveryWindow } from './types';

/* ------------------------------------------------------------------ */
/*  Delivery Zones                                                    */
/* ------------------------------------------------------------------ */

export async function listDeliveryZones(
  storeId: string,
  params: { page?: number; per_page?: number } = {},
): Promise<ApiResponse<DeliveryZone[]>> {
  const res = await apiClient.get<ApiResponse<DeliveryZone[]>>(
    `/stores/${storeId}/delivery-zones`,
    { params },
  );
  return res.data;
}

export async function getDeliveryZone(id: string): Promise<ApiResponse<DeliveryZone>> {
  const res = await apiClient.get<ApiResponse<DeliveryZone>>(`/delivery-zones/${id}`);
  return res.data;
}

export async function createDeliveryZone(
  storeId: string,
  data: Record<string, unknown>,
): Promise<ApiResponse<DeliveryZone>> {
  const res = await apiClient.post<ApiResponse<DeliveryZone>>(
    `/stores/${storeId}/delivery-zones`,
    data,
  );
  return res.data;
}

export async function updateDeliveryZone(
  id: string,
  data: Record<string, unknown>,
  version: number,
): Promise<ApiResponse<DeliveryZone>> {
  const res = await apiClient.put<ApiResponse<DeliveryZone>>(
    `/delivery-zones/${id}`,
    data,
    { headers: { 'If-Match': String(version) } },
  );
  return res.data;
}

/* ------------------------------------------------------------------ */
/*  Delivery Windows                                                  */
/* ------------------------------------------------------------------ */

export async function listDeliveryWindows(
  zoneId: string,
): Promise<ApiResponse<DeliveryWindow[]>> {
  const res = await apiClient.get<ApiResponse<DeliveryWindow[]>>(
    `/delivery-zones/${zoneId}/windows`,
  );
  return res.data;
}

export async function createDeliveryWindow(
  zoneId: string,
  data: {
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_active?: boolean;
  },
): Promise<ApiResponse<DeliveryWindow>> {
  const res = await apiClient.post<ApiResponse<DeliveryWindow>>(
    `/delivery-zones/${zoneId}/windows`,
    data,
  );
  return res.data;
}

export async function updateDeliveryWindow(
  id: string,
  data: Partial<{
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_active: boolean;
  }>,
): Promise<ApiResponse<DeliveryWindow>> {
  const res = await apiClient.put<ApiResponse<DeliveryWindow>>(
    `/delivery-windows/${id}`,
    data,
  );
  return res.data;
}

export async function deleteDeliveryWindow(id: string): Promise<ApiResponse<null>> {
  const res = await apiClient.delete<ApiResponse<null>>(`/delivery-windows/${id}`);
  return res.data;
}
