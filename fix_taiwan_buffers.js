const fs = require('fs');
const proj4 = require('proj4');

// The flawed string I used previously
proj4.defs("EPSG:3123_BAD", "+proj=tmerc +lat_0=10.66666666666667 +lon_0=121 +k=0.99995 +x_0=500000 +y_0=0 +ellps=clrk66 +towgs84=-127.62,-67.24,-47.04,-3.068,4.903,1.578,-1.06 +units=m +no_defs");

// The correct string
proj4.defs("EPSG:3123_GOOD", "+proj=tmerc +lat_0=0 +lon_0=121 +k=0.99995 +x_0=500000 +y_0=0 +ellps=clrk66 +towgs84=-127.62,-67.24,-47.04,-3.068,4.903,1.578,-1.06 +units=m +no_defs");

// 1. Transform WGS84_BAD back to EPSG:3123_BAD
const reverseBad = proj4("EPSG:4326", "EPSG:3123_BAD");
// 2. Transform EPSG:3123_BAD (which is actually the raw meters) to WGS84_GOOD
const applyGood = proj4("EPSG:3123_GOOD", "EPSG:4326");

function fixCoords(coords, depth) {
  if (depth === 0) {
    // Current coords are [lng, lat] pointing to Taiwan
    // Step 1: Revert back to original X/Y meters
    const rawMeters = reverseBad.forward([coords[0], coords[1]]);
    
    // Step 2: Apply the CORRECT math to get back to Cabanatuan
    const correctWgs84 = applyGood.forward([rawMeters[0], rawMeters[1]]);
    
    coords[0] = correctWgs84[0];
    coords[1] = correctWgs84[1];
  } else {
    for (let i = 0; i < coords.length; i++) {
      fixCoords(coords[i], depth - 1);
    }
  }
}

const basePath = "C:/Users/HP VICTUS/Downloads/Cab_evacuation project/Cab_evacuation project";
const filesToConvert = [
  `${basePath}/Buffer Analysis/500m buffer.geojson`,
  `${basePath}/Buffer Analysis/1km buffer.geojson`,
  `${basePath}/Buffer Analysis/2km buffer.geojson`
];

for (const filePath of filesToConvert) {
  try {
    const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    
    // We already removed the CRS in the previous run, so just apply the fix directly
    data.features.forEach(feature => {
      if (!feature.geometry || !feature.geometry.coordinates) return;
      const type = feature.geometry.type;
      let depth = 0;
      if (type === 'Point') depth = 0;
      else if (type === 'LineString' || type === 'MultiPoint') depth = 1;
      else if (type === 'Polygon' || type === 'MultiLineString') depth = 2;
      else if (type === 'MultiPolygon') depth = 3;

      fixCoords(feature.geometry.coordinates, depth);
    });

    fs.writeFileSync(filePath, JSON.stringify(data));
    console.log(`Successfully fixed: ${filePath}`);
  } catch (err) {
    console.error(`Failed on ${filePath}`, err);
  }
}
