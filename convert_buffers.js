const fs = require('fs');
const proj4 = require('proj4');

// Define PRS92 / Philippines zone 3
proj4.defs("EPSG:3123", "+proj=tmerc +lat_0=10.66666666666667 +lon_0=121 +k=0.99995 +x_0=500000 +y_0=0 +ellps=clrk66 +towgs84=-127.62,-67.24,-47.04,-3.068,4.903,1.578,-1.06 +units=m +no_defs +type=crs");

const transform = proj4("EPSG:3123", "EPSG:4326");

function convertCoords(coords, depth) {
  if (depth === 0) {
    const pt = transform.forward([coords[0], coords[1]]);
    coords[0] = pt[0];
    coords[1] = pt[1];
  } else {
    for (let i = 0; i < coords.length; i++) {
      convertCoords(coords[i], depth - 1);
    }
  }
}

// Ensure the path is correct depending on where we execute
const basePath = "C:/Users/HP VICTUS/Downloads/Cab_evacuation project/Cab_evacuation project";

const filesToConvert = [
  `${basePath}/Buffer Analysis/500m buffer.geojson`,
  `${basePath}/Buffer Analysis/1km buffer.geojson`,
  `${basePath}/Buffer Analysis/2km buffer.geojson`,
  `${basePath}/Isochrones/isochrones layer.geojson`
];

let convertedCount = 0;

for (const filePath of filesToConvert) {
  if (!fs.existsSync(filePath)) {
    console.log(`Skipping (not found): ${filePath}`);
    continue;
  }

  try {
    const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));

    // Check if it has custom CRS
    if (data.crs && data.crs.properties && data.crs.properties.name.includes('3123')) {
      console.log(`Converting: ${filePath}`);

      data.features.forEach(feature => {
        if (!feature.geometry || !feature.geometry.coordinates) return;
        const type = feature.geometry.type;
        let depth = 0;
        if (type === 'Point') depth = 0;
        else if (type === 'LineString' || type === 'MultiPoint') depth = 1;
        else if (type === 'Polygon' || type === 'MultiLineString') depth = 2;
        else if (type === 'MultiPolygon') depth = 3;

        convertCoords(feature.geometry.coordinates, depth);
      });

      // Remove the custom CRS to make it standard WGS84
      delete data.crs;

      // Save it back!
      fs.writeFileSync(filePath, JSON.stringify(data));
      convertedCount++;
      console.log(`Success: ${filePath}`);
    } else {
      console.log(`Already converted or no CRS found: ${filePath}`);
    }
  } catch (err) {
    console.error(`Failed on ${filePath}`, err);
  }
}

console.log(`\nConversion complete! Converted ${convertedCount} files.`);
