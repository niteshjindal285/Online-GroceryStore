import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';

const OrderDetails: React.FC = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const orders = JSON.parse(localStorage.getItem('orders') || '[]');
    const order = orders.find((o: { id: string }) => o.id === id);

    if (!order) {
        return <div className="p-6">Order not found</div>;
    }

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <button
                onClick={() => navigate(-1)}
                className="flex items-center text-gray-600 mb-4"
            >
                <ArrowLeft className="w-4 h-4 mr-1" />
                Back
            </button>

            <h1 className="text-xl font-semibold mb-4">Order Details</h1>

            <div className="bg-white rounded-xl p-4 shadow-sm space-y-4">
                <p><strong>Status:</strong> Delivered</p>
                <p><strong>Date:</strong> {new Date(parseInt(order.id)).toLocaleDateString()}</p>
                <p><strong>Total:</strong> ${order.total.toFixed(2)}</p>

                <div>
                    <h2 className="font-semibold mb-2">Items</h2>
                    {order.items.map((item: { name: string; quantity: number; price: number }, index: number) => (
                        <div key={index} className="flex justify-between text-sm">
                            <span>{item.name} x {item.quantity}</span>
                            <span>${(item.price * item.quantity).toFixed(2)}</span>
                        </div>
                    ))}
                </div>

                <div>
                    <h2 className="font-semibold mb-2">Delivery Address</h2>
                    <p className="text-sm text-gray-600">
                        {order.deliveryAddress.street}, {order.deliveryAddress.city},
                        {order.deliveryAddress.state} {order.deliveryAddress.zipCode}
                    </p>
                    <p className="text-sm">Phone: {order.deliveryAddress.phone}</p>
                </div>
            </div>
        </div>
    );
};

export default OrderDetails;
