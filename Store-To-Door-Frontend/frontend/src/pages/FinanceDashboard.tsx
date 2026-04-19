import React, { useState, useEffect } from 'react';
import api from '../api/config';

interface JournalLine {
    debit: number;
    credit: number;
}

interface JournalEntry {
    _id: string;
    date: string;
    description: string;
    lines: JournalLine[];
}

const FinanceDashboard = () => {
    const [entries, setEntries] = useState<JournalEntry[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchEntries = async () => {
            try {
                const res = await api.get('/finance/journal-entries');
                setEntries(res.data);
            } catch (err) {
                console.error(err);
            } finally {
                setLoading(false);
            }
        };
        fetchEntries();
    }, []);

    if (loading) return <div className="p-8 flex items-center justify-center"><div>Loading finance...</div></div>;

    const totalDebit = entries.reduce((sum, e) => sum + e.lines.reduce((lSum, l) => lSum + l.debit, 0), 0);
    const totalCredit = entries.reduce((sum, e) => sum + e.lines.reduce((lSum, l) => lSum + l.credit, 0), 0);

    return (
        <div className="p-8">
            <h1 className="text-3xl font-bold mb-8">Finance Dashboard (ERP)</h1>
            <div className="grid md:grid-cols-2 gap-4 mb-8">
                <div className="bg-green-500 text-white p-6 rounded-lg">
                    <h3>Total Debit</h3>
                    <p className="text-2xl">₹{totalDebit.toFixed(2)}</p>
                </div>
                <div className="bg-red-500 text-white p-6 rounded-lg">
                    <h3>Total Credit</h3>
                    <p className="text-2xl">₹{totalCredit.toFixed(2)}</p>
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full bg-white shadow-md rounded-lg">
                    <thead>
                        <tr className="bg-gray-100">
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {entries.slice(-5).map(e => {
                            const balance = e.lines.reduce((s, l) => s + l.debit - l.credit, 0);
                            return (
                                <tr key={e._id}>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{new Date(e.date).toLocaleDateString()}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{e.description}</td>
                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">₹{balance.toFixed(2)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default FinanceDashboard;

