import { Product, mockProducts } from '../data/mockProducts';

export const getProducts = (): Product[] => {
    try {
        const addedStr = localStorage.getItem('addedProducts');
        const addedProducts: Product[] = addedStr ? JSON.parse(addedStr) : [];

        const deletedStr = localStorage.getItem('deletedProductIds');
        const deletedIds: string[] = deletedStr ? JSON.parse(deletedStr) : [];

        const editedStr = localStorage.getItem('editedProducts');
        const editedProducts: Record<string, Product> = editedStr ? JSON.parse(editedStr) : {};

        const allBaseProducts = [...mockProducts, ...addedProducts];

        return allBaseProducts
            .filter(p => !deletedIds.includes(p.id))
            .map(p => editedProducts[p.id] ? editedProducts[p.id] : p);

    } catch (e) {
        console.error("Error reading from localStorage", e);
        return mockProducts;
    }
};

export const addProduct = (product: Product): Product => {
    try {
        const customProductsStr = localStorage.getItem('addedProducts');
        const customProducts: Product[] = customProductsStr ? JSON.parse(customProductsStr) : [];
        customProducts.push(product);
        localStorage.setItem('addedProducts', JSON.stringify(customProducts));
        return product;
    } catch (e) {
        console.error("Error writing to localStorage", e);
        return product;
    }
};

export const deleteProduct = (id: string): void => {
    try {
        const addedStr = localStorage.getItem('addedProducts');
        const addedProducts: Product[] = addedStr ? JSON.parse(addedStr) : [];
        const addedIndex = addedProducts.findIndex(p => p.id === id);

        if (addedIndex !== -1) {
            addedProducts.splice(addedIndex, 1);
            localStorage.setItem('addedProducts', JSON.stringify(addedProducts));
            return;
        }

        const deletedStr = localStorage.getItem('deletedProductIds');
        const deletedIds: string[] = deletedStr ? JSON.parse(deletedStr) : [];
        if (!deletedIds.includes(id)) {
            deletedIds.push(id);
            localStorage.setItem('deletedProductIds', JSON.stringify(deletedIds));
        }
    } catch (e) {
        console.error("Error deleting product", e);
    }
};

export const editProduct = (product: Product): void => {
    try {
        const addedStr = localStorage.getItem('addedProducts');
        const addedProducts: Product[] = addedStr ? JSON.parse(addedStr) : [];
        const addedIndex = addedProducts.findIndex(p => p.id === product.id);

        if (addedIndex !== -1) {
            addedProducts[addedIndex] = product;
            localStorage.setItem('addedProducts', JSON.stringify(addedProducts));
            return;
        }

        const editedStr = localStorage.getItem('editedProducts');
        const editedProducts: Record<string, Product> = editedStr ? JSON.parse(editedStr) : {};
        editedProducts[product.id] = product;
        localStorage.setItem('editedProducts', JSON.stringify(editedProducts));
    } catch (e) {
        console.error("Error editing product", e);
    }
};
