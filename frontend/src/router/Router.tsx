import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import FacilityList from '../routes/FacilityList';
import AdviceDetail from '../routes/AdviceDetail';

/** Centralised CSR routing (React Router v6). */
export default function AppRouter() {
  return (
    <BrowserRouter basename="/">
      <Routes>
        <Route path="/" element={<FacilityList />} />
        <Route path="/advice/:id" element={<AdviceDetail />} />
        <Route path="*" element={<Navigate to="/" />} />
      </Routes>
    </BrowserRouter>
  );
}
