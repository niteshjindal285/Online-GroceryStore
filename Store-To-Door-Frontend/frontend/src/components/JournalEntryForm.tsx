import React, { useState } from 'react';
import api from '../api/config';

interface Line {
    accountId: string;
    debit: number;
    credit: number;
    description: string;
}

const JournalEntryForm = () => {
    const [date, setDate] = useState('');
    const [description, setDescription] = useState('');
    const [lines, setLines] = useState<Line[]>([{ accountId: '', debit: 0, credit: 0, description: '' }]);
    const [loading, setLoading] = useState(false);

    const addLine = () => setLines([...lines, { accountId: '', debit: 0, credit: 0, description: '' }]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        try {
            await api.post('/finance/journal-entries', { date, description, lines });
            alert('Journal entry created!');
            // Reset form
            setDate('');
            setDescription('');
            setLines([{ accountId: '', debit: 0, credit: 0, description: '' }]);
        } catch {
            alert('Error creating entry');
        }
        setLoading(false);
    };

    return (
        <form onSubmit={handleSubmit} className="max-w-2xl mx-auto p-6 bg-white rounded-lg shadow-md">
            <h2 className="text-2xl font-bold mb-6">New Journal Entry</h2>
            <div className="space-y-4 mb-6">
                <input
                    type="date"
                    value={date}
                    onChange={e => setDate(e.target.value)}
                    className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                />
                <input
                    type="text"
                    placeholder="Description"
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                    className="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>
            <div className="space-y-3 mb-6">
                {lines.map((line, idx) => (
                    <div key={idx} className="grid grid-cols-1 md:grid-cols-4 gap-3 p-4 bg-gray-50 rounded-lg">
                        <input
                            placeholder="Account ID"
                            value={line.accountId}
                            onChange={e => {
                                const newLines = [...lines];
                                newLines[idx].accountId = e.target.value;
                                setLines(newLines);
                            }}
                            className="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                        <input
                            type="number"
                            placeholder="Debit"
                            value={line.debit}
                            onChange={e => {
                                const newLines = [...lines];
                                newLines[idx].debit = parseFloat(e.target.value) || 0;
                                setLines(newLines);
                            }}
                            className="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            step="0.01"
                        />
                        <input
                            type="number"
                            placeholder="Credit"
                            value={line.credit}
                            onChange={e => {
                                const newLines = [...lines];
                                newLines[idx].credit = parseFloat(e.target.value) || 0;
                                setLines(newLines);
                            }}
                            className="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            step="0.01"
                        />
                        <input
                            placeholder="Description"
                            value={line.description}
                            onChange={e => {
                                const newLines = [...lines];
                                newLines[idx].description = e.target.value;
                                setLines(newLines);
                            }}
                            className="p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                ))}
            </div>
            <div className="flex gap-3">
                <button type="button" onClick={addLine} className="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-medium py-3 px-6 rounded-lg transition-colors duration-200">
                    Add Line
                </button>
                <button type="submit" disabled={loading} className="flex-1 bg-green-500 hover:bg-green-600 disabled:bg-green-400 text-white font-medium py-3 px-6 rounded-lg transition-colors duration-200">
                    {loading ? 'Saving...' : 'Save Entry'}
                </button>
            </div>
        </form>
    );
};

export default JournalEntryForm;

