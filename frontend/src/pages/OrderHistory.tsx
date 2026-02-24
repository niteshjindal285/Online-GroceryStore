import React, { useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

const OrderHistory: React.FC = () => {
  const navigate = useNavigate();

  const [orders] = useState(() => {
    const savedOrders = localStorage.getItem('orders');
    return savedOrders ? JSON.parse(savedOrders) : [];
  });

  return (
    <div className="min-h-screen bg-gray-50 p-6">
      <h1 className="text-xl font-semibold mb-4">Past Orders</h1>

      <div className="space-y-4">
        {orders.map((order: { id: string; items: { image?: string; name: string }[] }) => (
          <div
            key={order.id}
            onClick={() => navigate(`/orders/${order.id}`)}
            className="bg-white rounded-xl p-4 flex justify-between items-center shadow-sm cursor-pointer hover:bg-gray-50 transition"
          >
            {/* Left */}
            <div>
              <div className="flex items-center gap-2">
                <span className="font-semibold text-gray-900">Delivered</span>
                <span className="text-sm text-gray-500">
                  {new Date(parseInt(order.id)).toLocaleDateString()}
                </span>
              </div>

              <div className="flex gap-2 mt-2">
                {order.items.slice(0, 3).map((item: { image?: string; name: string }, index: number) => (
                  <div
                    key={index}
                    className="w-12 h-12 border rounded-lg bg-gray-50 flex items-center justify-center"
                  >
                    <img
                      src={item.image || '/placeholder.png'}
                      alt={item.name}
                      className="w-10 h-10 object-contain"
                    />
                  </div>
                ))}

                {order.items.length > 3 && (
                  <div className="w-12 h-12 border rounded-lg flex items-center justify-center text-sm">
                    +{order.items.length - 3}
                  </div>
                )}
              </div>
            </div>

            {/* Right arrow */}
            <ChevronRight className="text-gray-400" />
          </div>
        ))}
      </div>
    </div>
  );
};

export default OrderHistory;

