import React from 'react';

interface InventoryItem {
    _id: string;
    name: string;
    quantity: number;
    location: string;
}

interface InventoryListProps {
    items: InventoryItem[];
}

const InventoryList: React.FC<InventoryListProps> = ({ items }) => {
    return (
        <div className="mt-6">
            <h3 className="text-xl font-semibold mb-4">Inventory Items</h3>
            <div className="overflow-x-auto">
                <table className="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr className="bg-gray-50">
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => (
                            <tr key={item._id} className="border-b">
                                <td className="px-6 py-4">{item.name}</td>
                                <td className="px-6 py-4 font-medium">{item.quantity}</td>
                                <td className="px-6 py-4">{item.location}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default InventoryList;

