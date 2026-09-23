import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './hooks/AuthProvider'
import { ToastProvider } from './hooks/ToastProvider'
import AppLayout from './layouts/AppLayout'
import RequireAdmin from './layouts/RequireAdmin'
import AddReminder from './pages/AddReminder'
import AddUser from './pages/AddUser'
import Dashboard from './pages/Dashboard'
import EditReminder from './pages/EditReminder'
import EditUser from './pages/EditUser'
import Login from './pages/Login'
import ManageUsers from './pages/ManageUsers'
import Reminders from './pages/Reminders'

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route element={<AppLayout />}>
              <Route index element={<Dashboard />} />
              <Route path="reminders" element={<Reminders />} />
              <Route path="reminders/new" element={<AddReminder />} />
              <Route path="reminders/:id/edit" element={<EditReminder />} />
              <Route element={<RequireAdmin />}>
                <Route path="users" element={<ManageUsers />} />
                <Route path="users/new" element={<AddUser />} />
                <Route path="users/:id/edit" element={<EditUser />} />
              </Route>
            </Route>
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  )
}
