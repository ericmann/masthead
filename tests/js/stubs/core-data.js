// Unit tests never need the real core-data store, whose dependency tree ships ESM-only packages.
module.exports = { store: 'core' };
