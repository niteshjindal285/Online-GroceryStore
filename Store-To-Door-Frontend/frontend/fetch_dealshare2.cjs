const https = require('https');
const fs = require('fs');

https.get('https://www.dealshare.in/category/cn/grocery/cid/719', (res) => {
    let data = '';
    res.on('data', chunk => {
        data += chunk;
    });
    res.on('end', () => {
        fs.writeFileSync('dealshare_grocery.html', data);
        console.log('Saved HTML, length:', data.length);

        try {
            const nextDataMatch = data.match(/<script id="__NEXT_DATA__" type="application\/json">([\s\S]+?)<\/script>/);
            if (nextDataMatch && nextDataMatch[1]) {
                fs.writeFileSync('dealshare_next_data.json', nextDataMatch[1]);
                console.log('Extracted __NEXT_DATA__');
            } else {
                console.log('__NEXT_DATA__ not found');
            }
        } catch (e) {
            console.error('Error extracting __NEXT_DATA__', e.message);
        }
    });
}).on('error', (err) => {
    console.error('Error fetching URL:', err.message);
});
