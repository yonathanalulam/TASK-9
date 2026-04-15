/* ------------------------------------------------------------------ */
/*  Generic API envelopes (matches backend snake_case convention)      */
/* ------------------------------------------------------------------ */

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface ApiResponse<T> {
  data: T;
  meta: {
    request_id: string;
    timestamp: string;
    pagination?: PaginationMeta;
  };
  error: null;
}

export interface ErrorResponse {
  data: null;
  meta: {
    request_id: string;
    timestamp: string;
  };
  error: {
    code: string;
    message: string;
    details: Record<string, string>;
  };
}

/* ------------------------------------------------------------------ */
/*  Auth                                                              */
/* ------------------------------------------------------------------ */

export interface RoleAssignment {
  id?: string;
  role: string;
  role_display_name?: string;
  scope_type: string;
  scope_id: string | null;
  effective_from?: string;
  effective_until?: string | null;
}

export interface LoginResponse {
  token: string;
  user: User & { roles: RoleAssignment[] };
}

/* ------------------------------------------------------------------ */
/*  Users                                                             */
/* ------------------------------------------------------------------ */

export interface User {
  id: string;
  username: string;
  display_name: string;
  status: string;
  last_login_at: string | null;
  created_at: string;
  updated_at: string;
  version: number;
}

/* ------------------------------------------------------------------ */
/*  Regions                                                           */
/* ------------------------------------------------------------------ */

export interface Region {
  id: string;
  code: string;
  name: string;
  parent_id: string | null;
  hierarchy_level: number;
  effective_from: string;
  effective_until: string | null;
  is_active: boolean;
  version: number;
}

/* ------------------------------------------------------------------ */
/*  Stores                                                            */
/* ------------------------------------------------------------------ */

export interface Store {
  id: string;
  code: string;
  name: string;
  store_type: string;
  status: string;
  region_id: string;
  timezone: string;
  address_line_1: string | null;
  address_line_2: string | null;
  city: string | null;
  postal_code: string | null;
  latitude: string | null;
  longitude: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  version: number;
}

/* ------------------------------------------------------------------ */
/*  Delivery Zones & Windows                                          */
/* ------------------------------------------------------------------ */

export interface DeliveryZone {
  id: string;
  name: string;
  store_id: string;
  status: string;
  min_order_threshold: string;
  delivery_fee: string;
  is_active: boolean;
  version: number;
}

export interface DeliveryWindow {
  id: string;
  zone_id: string;
  day_of_week: number;
  start_time: string;
  end_time: string;
  is_active: boolean;
}

/* ------------------------------------------------------------------ */
/*  Content                                                           */
/* ------------------------------------------------------------------ */

export interface ContentItem {
  id: string;
  content_type: string;
  title: string;
  body: string;
  author_name: string;
  status: string;
  published_at: string | null;
  store_id: string | null;
  region_id: string | null;
  view_count: number;
  reply_count: number;
  tags: string[];
  version: number;
  created_at: string;
  updated_at: string;
}

export interface ContentVersion {
  id: string;
  content_item_id: string;
  version_number: number;
  title: string;
  body: string;
  tags: string[];
  content_type: string;
  status_at_creation: string;
  change_reason: string | null;
  is_rollback: boolean;
  rolled_back_to_version_id: string | null;
  created_by: string;
  created_at: string;
}

/* ------------------------------------------------------------------ */
/*  Search                                                            */
/* ------------------------------------------------------------------ */

export interface SearchResult {
  id: string;
  content_type: string;
  title: string;
  author_name: string;
  published_at: string | null;
  tags: string[];
  view_count: number;
  reply_count: number;
  snippet: string;
  highlight_title: string;
  relevance_score: number;
}
