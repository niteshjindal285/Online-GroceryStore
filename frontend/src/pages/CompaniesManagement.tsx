import React, { useState, useEffect, useCallback } from 'react';
import { 
  Building2, Users, Plus, Edit, Trash2, X, 
  Search, ChevronRight, AlertCircle, 
  Briefcase, Shield, Settings, Loader2
} from 'lucide-react';
import api from '../api/config';
import { useToast } from '../contexts/ToastContext';

interface User {
  _id: string;
  name: string;
  email: string;
}

interface Company {
  _id: string;
  name: string;
  code: string;
  managerId: User | null;
  users: string[];
  isActive: boolean;
  createdAt: string;
}

const CompaniesManagement: React.FC = () => {
  const { showToast } = useToast();
  const [companies, setCompanies] = useState<Company[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  
  // Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCompany, setEditingCompany] = useState<Company | null>(null);
  const [formData, setFormData] = useState({
    name: '',
    code: '',
    managerId: '',
    isActive: true
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    try {
      const [compRes, userRes] = await Promise.all([
        api.get('/companies'),
        api.get('/users')
      ]);
      setCompanies(compRes.data);
      setUsers(userRes.data);
    } catch (error: unknown) {
      const err = error as { response?: { status?: number } };
      console.error('Fetch Data Error:', err);
      if (err.response?.status === 401) {
        showToast('Your session has expired. Please login again.', 'error');
      } else if (err.response?.status === 403) {
        showToast('Access Denied: You do not have permission to view this page.', 'error');
      } else {
        showToast('Failed to load companies or users. Please check your connection.', 'error');
      }
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleOpenAdd = () => {
    setEditingCompany(null);
    setFormData({ name: '', code: '', managerId: '', isActive: true });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (comp: Company) => {
    setEditingCompany(comp);
    setFormData({
      name: comp.name,
      code: comp.code,
      managerId: comp.managerId?._id || '',
      isActive: comp.isActive
    });
    setIsModalOpen(true);
  };

  const handleDelete = async (id: string) => {
    if (!window.confirm('Are you sure you want to delete this company? All associated data might be affected.')) return;
    try {
      await api.delete(`/companies/${id}`);
      showToast('Company deleted successfully', 'success');
      fetchData();
    } catch {
      showToast('Failed to delete company', 'error');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const { managerId, ...rest } = formData;
      const dataToSave = !managerId ? rest : formData;

      if (editingCompany) {
        await api.put(`/companies/${editingCompany._id}`, dataToSave);
        // Also update manager if changed
        if (formData.managerId) {
          await api.put(`/companies/${editingCompany._id}/users`, { 
            userId: formData.managerId, 
            asManager: true 
          });
        }
        showToast('Company updated successfully', 'success');
      } else {
        const res = await api.post('/companies', dataToSave);
        if (formData.managerId) {
          await api.put(`/companies/${res.data._id}/users`, { 
            userId: formData.managerId, 
            asManager: true 
          });
        }
        showToast('Company created successfully', 'success');
      }
      setIsModalOpen(false);
      fetchData();
    } catch {
      showToast('Failed to save company', 'error');
    }
  };

  const filteredCompanies = companies.filter(c => 
    c.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
    c.code.toLowerCase().includes(searchTerm.toLowerCase())
  );

  if (loading && companies.length === 0) {
    return (
      <div className="min-h-[60vh] flex flex-col items-center justify-center">
        <Loader2 className="h-10 w-10 text-emerald-500 animate-spin mb-4" />
        <p className="text-gray-500 font-medium">Loading your companies...</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#f8fafc] p-4 sm:p-6 lg:p-8">
      <div className="max-w-7xl mx-auto">
        
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
          <div>
            <div className="flex items-center gap-2 text-xs text-gray-400 mb-1">
              <span>ERP</span>
              <ChevronRight className="h-3 w-3" />
              <span className="text-emerald-600 font-medium">Company Management</span>
            </div>
            <h1 className="text-3xl font-bold text-gray-900 font-display flex items-center gap-3">
              <div className="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <Building2 className="h-6 w-6 text-white" />
              </div>
              Companies Management
            </h1>
          </div>
          
          <button 
            onClick={handleOpenAdd}
            className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold px-6 py-3 rounded-xl transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 hover:-translate-y-0.5"
          >
            <Plus className="h-5 w-5" /> Add Company
          </button>
        </div>

        {/* Search and Filters */}
        <div className="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
          <div className="relative w-full sm:w-96">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
            <input 
              type="text" 
              placeholder="Search companies by name or code..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-11 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all"
            />
          </div>
          <div className="text-xs font-bold text-gray-400 uppercase tracking-widest px-4 py-2 border border-dashed border-gray-200 rounded-xl">
             Total: <span className="text-emerald-600 ml-1">{companies.length}</span>
          </div>
        </div>

        {/* Table/List */}
        <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="min-w-full">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-100">
                  <th className="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">Company Info</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">Manager</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">Users</th>
                  <th className="px-6 py-4 text-left text-xs font-bold text-gray-400 uppercase tracking-widest">Status</th>
                  <th className="px-6 py-4 text-right text-xs font-bold text-gray-400 uppercase tracking-widest">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-50">
                {filteredCompanies.length > 0 ? filteredCompanies.map((company) => (
                  <tr key={company._id} className="hover:bg-gray-50/50 transition-colors group">
                    <td className="px-6 py-5">
                      <div className="flex items-center gap-4">
                        <div className="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 group-hover:bg-emerald-100 transition-colors">
                          <Briefcase className="h-6 w-6" />
                        </div>
                        <div>
                          <p className="font-bold text-gray-900 group-hover:text-emerald-700 transition-colors">{company.name}</p>
                          <p className="text-xs font-mono text-gray-400 mt-0.5">{company.code.toUpperCase()}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-5">
                      {company.managerId ? (
                        <div className="flex items-center gap-2">
                          <div className="w-7 h-7 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-[10px] font-bold">
                            {company.managerId.name[0].toUpperCase()}
                          </div>
                          <div>
                            <p className="text-sm font-semibold text-gray-700">{company.managerId.name}</p>
                            <p className="text-[10px] text-gray-400">{company.managerId.email}</p>
                          </div>
                        </div>
                      ) : (
                        <span className="text-xs text-gray-400 italic">No manager assigned</span>
                      )}
                    </td>
                    <td className="px-6 py-5">
                      <div className="flex items-center gap-1.5">
                        <Users className="h-4 w-4 text-gray-300" />
                        <span className="text-sm font-bold text-gray-600">{company.users.length}</span>
                        <span className="text-xs text-gray-400">Users</span>
                      </div>
                    </td>
                    <td className="px-6 py-5">
                      <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border ${
                        company.isActive 
                          ? 'bg-emerald-50 text-emerald-700 border-emerald-100' 
                          : 'bg-rose-50 text-rose-700 border-rose-100'
                      }`}>
                         <span className={`w-1.5 h-1.5 rounded-full ${company.isActive ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                         {company.isActive ? 'Active' : 'Deactivated'}
                      </span>
                    </td>
                    <td className="px-6 py-5 text-right">
                      <div className="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button 
                          onClick={() => handleOpenEdit(company)}
                          className="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400 hover:bg-emerald-50 hover:text-emerald-600 transition-all shadow-sm"
                        >
                          <Edit className="h-4 w-4" />
                        </button>
                        <button 
                          onClick={() => handleDelete(company._id)}
                          className="w-9 h-9 flex items-center justify-center rounded-xl bg-gray-50 text-gray-400 hover:bg-rose-50 hover:text-rose-600 transition-all shadow-sm"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan={5} className="px-6 py-20 text-center">
                      <div className="w-16 h-16 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <AlertCircle className="h-8 w-8 text-gray-300" />
                      </div>
                      <p className="text-gray-500 font-bold text-lg">No companies found</p>
                      <p className="text-gray-400 text-sm mt-1 max-w-xs mx-auto">
                        Try adjusting your search or add a new business entity to get started.
                      </p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>

      </div>

      {/* Modal */}
      {isModalOpen && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={() => setIsModalOpen(false)} />
          <div className="relative bg-white w-full max-w-md rounded-3xl shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div className="p-6 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white">
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                  {editingCompany ? <Edit className="h-5 w-5" /> : <Plus className="h-5 w-5" />}
                </div>
                <div>
                  <h3 className="text-lg font-bold text-gray-900">{editingCompany ? 'Edit Company' : 'Register Company'}</h3>
                  <p className="text-xs text-gray-400">Company registration and setup</p>
                </div>
              </div>
              <button 
                onClick={() => setIsModalOpen(false)}
                className="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-gray-100 transition-colors"
              >
                <X className="h-5 w-5 text-gray-400" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Company Name</label>
                <div className="relative">
                  <Briefcase className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input 
                    type="text" 
                    required
                    placeholder="e.g. Balaji Trading Company"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm transition-all"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Internal Code</label>
                <div className="relative">
                  <Shield className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input 
                    type="text" 
                    required
                    placeholder="e.g. BTC-MAIN"
                    value={formData.code}
                    onChange={(e) => setFormData({ ...formData, code: e.target.value.toUpperCase() })}
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm font-mono transition-all"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1.5 ml-1">Assign Manager</label>
                <div className="relative">
                  <Settings className="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <select 
                    value={formData.managerId}
                    onChange={(e) => setFormData({ ...formData, managerId: e.target.value })}
                    className="w-full pl-10 pr-10 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 text-sm appearance-none cursor-pointer transition-all"
                  >
                    <option value="">Select a manager...</option>
                    {users.map(u => (
                      <option key={u._id} value={u._id}>{u.name} ({u.email})</option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="pt-2">
                <label className="relative inline-flex items-center cursor-pointer group">
                  <input 
                    type="checkbox" 
                    checked={formData.isActive}
                    onChange={(e) => setFormData({ ...formData, isActive: e.target.checked })}
                    className="sr-only peer" 
                  />
                  <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 group-hover:shadow-[0_0_0_4px_rgba(16,185,129,0.1)] transition-all"></div>
                  <span className="ml-3 text-sm font-bold text-gray-500 group-hover:text-emerald-700 transition-colors">Active Business Entity</span>
                </label>
              </div>

              <div className="pt-6 flex gap-3">
                <button 
                  type="button" 
                  onClick={() => setIsModalOpen(false)}
                  className="flex-1 px-4 py-3 border border-gray-100 text-gray-500 font-bold rounded-2xl hover:bg-gray-50 transition-colors text-sm"
                >
                  Cancel
                </button>
                <button 
                  type="submit"
                  className="flex-1 px-4 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold rounded-2xl shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 transition-all text-sm"
                >
                  {editingCompany ? 'Save Changes' : 'Register Business'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default CompaniesManagement;
