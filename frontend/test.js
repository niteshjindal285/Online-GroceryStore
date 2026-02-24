import fs from 'fs';

const html = fs.readFileSync('dealshare_raw.html', 'utf-8');

let pos = html.indexOf('dealDetailsList');
console.log('dealDetailsList position:', pos);

if (pos !== -1) {
    let snippet = html.substring(pos - 20, pos + 100);
    console.log('Snippet:', snippet);
}

// Global regex to capture all titles and prices in the HTML regardless of exact structure
const titleMatches = [...html.matchAll(/\\\"title\\\":\\\"([^\\\"]+)\\\"/g)];
const priceMatches = [...html.matchAll(/\\\"price\\\":(\d+)/g)];
const imgMatches = [...html.matchAll(/\\\"image\\\":\\\"([^\\\"]+)\\\"/g)];

console.log(`Found ${titleMatches.length} titles`);
console.log(`Found ${priceMatches.length} prices`);
console.log(`Found ${imgMatches.length} images`);

if (titleMatches.length > 0) console.log(titleMatches[0][1]);
