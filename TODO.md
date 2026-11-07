# TODO

## Dimension groups: deleting leaves group codes on dimensions

`DimensionGroupResource::delete()` only sets the status of the group to `deleted`. The dimensions
that were in the group keep a reference to it: after deleting the test groups on company 1002, a
customer read (`<dimension>` DEB) still showed the deleted group codes, e.g. customer 1000 with
`ZZSDK1` and customers 1001 and 1002 with `EXAMPLE-999`.

To decide:

- Should `delete()` first save the group with no dimensions (`<dimensions/>`) and then delete it,
  so no dimension keeps a reference to a deleted group? Test this live on company 1002: does
  emptying the group remove the group code from the dimensions, and does a later re-create with
  the same code bring the old references back?
- If so: update `delete()`, the unit test (three requests instead of two), the write lifecycle test
  (check a customer before and after), the docs in `docs/src/content/docs/guides/master-data.md`
  and the delete example.
- Clean up the leftover group codes on customers 1000–1002 on company 1002.

See `src/DimensionGroup/DimensionGroupResource.php` (`delete()`).
