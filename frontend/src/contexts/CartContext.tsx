import React, { createContext, useContext, useEffect, useState } from 'react';
import { useAuth } from './AuthContext';
import api from '../api/config';

interface CartItem {
  id: string;
  name: string;
  price: number;
  quantity: number;
  image: string;
  category: string;
  rating?: number;
  discount?: number;
}

interface CartContextType {
  items: CartItem[];
  addToCart: (item: Omit<CartItem, 'quantity'>) => void;
  removeFromCart: (id: string) => void;
  updateQuantity: (id: string, quantity: number) => void;
  clearCart: () => void;
  getTotalPrice: () => number;
  getTotalItems: () => number;
}

const CartContext = createContext<CartContextType | undefined>(undefined);

export const CartProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [items, setItems] = useState<CartItem[]>([]);

  const { user } = useAuth();

  // Load from local storage initially
  useEffect(() => {
    const savedCart = localStorage.getItem('cart');
    if (savedCart && !user) {
      setItems(JSON.parse(savedCart));
    }
  }, []);

  // Fetch cart from backend when user logs in
  useEffect(() => {
    if (user) {
      api.get('/cart').then((res) => {
        if (res.data && res.data.items) {
          const mappedItems: CartItem[] = res.data.items
            .filter((item: any) => item.product) // Filter out null products
            .map((item: any) => ({
              id: item.product._id,
              name: item.product.name,
              price: item.product.price,
              image: item.product.image,
              category: item.product.category,
              rating: item.product.rating,
              discount: item.product.discount,
              quantity: item.qty
            }));
          setItems(mappedItems);
        }
      }).catch(err => console.error("Failed to load user cart:", err));
    }
  }, [user]);

  // Sync to local storage and backend on changes
  useEffect(() => {
    localStorage.setItem('cart', JSON.stringify(items));

    // Only save to backend if user is logged in and we've already initialized
    if (user) {
      const backendItems = items.map(item => ({ product: item.id, qty: item.quantity }));
      api.post('/cart', { items: backendItems }).catch(err => console.error("Failed to sync cart:", err));
    }
  }, [items, user]);

  const addToCart = (item: Omit<CartItem, 'quantity'>) => {
    setItems(prevItems => {
      const existingItem = prevItems.find(i => i.id === item.id);
      if (existingItem) {
        return prevItems.map(i =>
          i.id === item.id ? { ...i, quantity: i.quantity + 1 } : i
        );
      }
      return [...prevItems, { ...item, quantity: 1 }];
    });
  };

  const removeFromCart = (id: string) => {
    setItems(prevItems => prevItems.filter(item => item.id !== id));
  };

  const updateQuantity = (id: string, quantity: number) => {
    if (quantity === 0) {
      removeFromCart(id);
      return;
    }
    setItems(prevItems =>
      prevItems.map(item =>
        item.id === id ? { ...item, quantity } : item
      )
    );
  };

  const clearCart = () => {
    setItems([]);
  };

  const getTotalPrice = () => {
    return items.reduce((total, item) => total + item.price * item.quantity, 0);
  };

  const getTotalItems = () => {
    return items.reduce((total, item) => total + item.quantity, 0);
  };

  return (
    <CartContext.Provider value={{
      items,
      addToCart,
      removeFromCart,
      updateQuantity,
      clearCart,
      getTotalPrice,
      getTotalItems
    }}>
      {children}
    </CartContext.Provider>
  );
};

export const useCart = () => {
  const context = useContext(CartContext);
  if (context === undefined) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
};