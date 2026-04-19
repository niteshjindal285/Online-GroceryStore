const fs = require('fs');
const cheerio = require('cheerio');

const html = fs.readFileSync('dealshare_grocery.html', 'utf8');
const $ = cheerio.load(html);

const products = [];

$('.BauDealCard_cardContainer__YkCMh').each((i, el) => {
    const $el = $(el);
    const id = $el.attr('id');
    const name = $el.find('.BauDealCard_cardTitleContainer___Qh3b').text().trim();
    const price = parseInt($el.find('.BauDealCard_priceText__bt1qV').text().replace(/[^0-9]/g, '')) || 0;
    const mrp = parseInt($el.find('.BauDealCard_mrpText__0OFVz').text().replace(/[^0-9]/g, '')) || price;
    let image = $el.find('.BauDealCard_cardImage__mItG_').attr('src');

    // Clean up TR transformation query parameters to get the base image
    if (image && image.includes('?')) {
        image = image.split('?')[0];
    }

    const offTag = $el.find('.BauDealCard_offTag__722c_').text().replace(/[^0-9]/g, '');
    const discount = parseInt(offTag) || 0;

    if (name && id) {
        products.push({
            id: id,
            name: name,
            price: price,
            category: 'grocery',
            image: image,
            rating: parseFloat((4.0 + Math.random() * 0.9).toFixed(1)), // Fake rating 4.0 - 4.9
            discount: discount,
            inStock: true
        });
    }
});

console.log('Found', products.length, 'products mapped from HTML');
fs.writeFileSync('dealshare_extracted.json', JSON.stringify(products, null, 2));
