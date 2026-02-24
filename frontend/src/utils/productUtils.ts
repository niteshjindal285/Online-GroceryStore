import api from '../api/config';
import { Product } from '../data/mockProducts';

// GET all products from the real MongoDB database
export const getProducts = async (): Promise<Product[]> => {
    try {
        const response = await api.get('/products');

        // Map _id to id to match the frontend Product interface
        return response.data.map((p: any) => ({
            ...p,
            id: p._id
        }));
    } catch (e) {
        console.error("Error fetching products from API", e);
        return [];
    }
};

// POST new product to MongoDB
export const addProduct = async (productData: Omit<Product, 'id'>): Promise<Product | null> => {
    try {
        const response = await api.post('/products', productData);
        return {
            ...response.data,
            id: response.data._id
        };
    } catch (e) {
        console.error("Error adding product to API", e);
        return null;
    }
};

// DELETE product from MongoDB
export const deleteProduct = async (id: string): Promise<boolean> => {
    try {
        // Warning: The backend doesn't currently have a DELETE route, this simulates hitting it
        // A real implementation requires updating backend routes too.
        await api.delete(`/products/${id}`);
        return true;
    } catch (e) {
        console.error("Error deleting product", e);
        return false;
    }
};

// PUT product update
export const editProduct = async (id: string, updatedData: Partial<Product>): Promise<Product | null> => {
    try {
        const response = await api.put(`/products/${id}`, updatedData);
        return {
            ...response.data,
            id: response.data._id
        };
    } catch (e) {
        console.error("Error editing product", e);
        return null;
    }
};
