import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { CartProvider } from './contexts/CartContext';
import { ToastProvider } from './contexts/ToastContext';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import ErrorBoundary from './components/ErrorBoundary';
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';
import SignupPage from './pages/SignupPage';
import UserDashboard from './pages/UserDashboard';
import ProductDetail from './pages/ProductDetail';
import CartPage from './pages/CartPage';
import CheckoutPage from './pages/CheckoutPage';
// import OrderHistory from './pages/OrderHistory';
import UserProfile from './pages/UserProfile';
import AdminDashboard from './pages/AdminDashboard';
import VendorDashboard from './pages/VendorDashboard';
import ContactPage from './pages/ContactPage';
import AboutPage from './pages/AboutPage';
import NotFoundPage from './pages/NotFoundPage';
import ProtectedRoute from './components/ProtectedRoute';
import OrderHistory from './pages/OrderHistory';
import OrderDetails from './pages/OrderDetails';
import SupplierManagement from './pages/SupplierManagement';
import CategoryManagement from './pages/CategoryManagement';
import CustomerManagement from './pages/CustomerManagement';
import BillingPage from './pages/BillingPage';
import BillingHistory from './pages/BillingHistory';
import CompaniesManagement from './pages/CompaniesManagement';
import InventoryDashboard from './pages/InventoryDashboard';

function App() {
  return (
    <ErrorBoundary>
      <AuthProvider>
        <CartProvider>
          <ToastProvider>
            <Router basename="/Online-GroceryStore">
              <div className="min-h-screen bg-gradient-to-b from-slate-50 via-white to-slate-50">
                <Navbar />
                <main className="pb-16">
                  <Routes>
                    <Route path="/" element={<Navigate to="/home" replace />} />
                    <Route path="/home" element={<HomePage />} />
                    <Route path="/login" element={<LoginPage />} />
                    <Route path="/signup" element={<SignupPage />} />
                    <Route path="/products/:id" element={<ProductDetail />} />
                    <Route path="/cart" element={<CartPage />} />
                    <Route path="/contact" element={<ContactPage />} />
                    <Route path="/about" element={<AboutPage />} />

                    {/* Protected Routes */}
                    <Route path="/dashboard" element={
                      <ProtectedRoute>
                        <UserDashboard />
                      </ProtectedRoute>
                    } />
                    <Route path="/checkout" element={
                      <ProtectedRoute requiredRole="customer">
                        <CheckoutPage />
                      </ProtectedRoute>
                    } />
                    <Route path="/orders" element={
                      <ProtectedRoute>
                        <OrderHistory />
                      </ProtectedRoute>
                    } />
                    <Route path="/orders/:id" element={
                      <ProtectedRoute>
                        <OrderDetails />
                      </ProtectedRoute>
                    } />
                    <Route path="/profile" element={
                      <ProtectedRoute>
                        <UserProfile />
                      </ProtectedRoute>
                    } />
                    <Route path="/admin" element={
                      <ProtectedRoute requiredRole="admin">
                        <AdminDashboard />
                      </ProtectedRoute>
                    } />
                    <Route path="/vendor" element={
                      <ProtectedRoute requiredRole="vendor">
                        <VendorDashboard />
                      </ProtectedRoute>
                    } />

                    {/* ERP Routes */}
                    <Route path="/erp/categories" element={
                      <ProtectedRoute requiredRole="admin">
                        <CategoryManagement />
                      </ProtectedRoute>
                    } />
                    <Route path="/erp/inventory" element={
                      <ProtectedRoute requiredRole="admin">
                        <InventoryDashboard />
                      </ProtectedRoute>
                    } />
                    <Route path="/erp/customers" element={
                      <ProtectedRoute requiredRole="admin">
                        <CustomerManagement />
                      </ProtectedRoute>
                    } />
                    <Route path="/erp/suppliers" element={
                      <ProtectedRoute requiredRole="admin">
                        <SupplierManagement />
                      </ProtectedRoute>
                    } />
                    <Route path="/erp/billing" element={
                      <ProtectedRoute requiredRole="admin">
                        <BillingPage />
                      </ProtectedRoute>
                    } />
                    <Route path="/erp/billing-history" element={
                      <ProtectedRoute requiredRole="admin">
                        <BillingHistory />
                      </ProtectedRoute>
                    } />
                    <Route path="/erp/companies" element={
                      <ProtectedRoute requiredRole="admin">
                        <CompaniesManagement />
                      </ProtectedRoute>
                    } />

                    <Route path="*" element={<NotFoundPage />} />
                  </Routes>
                </main>
                <Footer />
              </div>
            </Router>
          </ToastProvider>
        </CartProvider>
      </AuthProvider>
    </ErrorBoundary>
  );
}

export default App;