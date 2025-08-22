import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import { Suspense, lazy } from 'react';

const FacilitySearchPage = lazy(() => import('../pages/FacilitySearchPage'));
const FacilityDetailPage = lazy(() => import('../pages/FacilityDetailPage'));
const DashboardPage = lazy(() => import('../pages/DashboardPage'));

const router = createBrowserRouter([
  { path: '/', element: <Suspense fallback="…"><FacilitySearchPage /></Suspense> },
  { path: '/facility/:id', element: <Suspense fallback="…"><FacilityDetailPage /></Suspense> },
  { path: '/dashboard', element: <Suspense fallback="…"><DashboardPage /></Suspense> },
]);

export function AppRouter() {
  return <RouterProvider router={router} />;
}
