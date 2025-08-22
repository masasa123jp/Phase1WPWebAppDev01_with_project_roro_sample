/**
 * 汎用ページネーションフック。
 */
import { useState } from 'react';

export function usePagination<T>(items:T[], pageSize:number = 10) {
  const [page, setPage] = useState(1);
  const max = Math.ceil(items.length / pageSize);
  const slice = items.slice((page-1)*pageSize, page*pageSize);

  return { page, max, slice, next:()=>setPage(Math.min(max,page+1)), prev:()=>setPage(Math.max(1,page-1)) };
}
