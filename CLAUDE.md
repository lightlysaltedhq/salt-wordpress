# Salt for WordPress

Salt for WordPress: the private, opinionated WordPress parent theme for the Lightly Salted fleet, customised per site through child themes.

## Planning and the next piece of work

This repository has no build register or decision log, and must not get one again. Planning lives
in Notion; records live in the private docs repository.

- **Plan of record:** Notion. Product: [Salt for WordPress](https://app.notion.com/p/3f12cdcdfb54818ba656fc2f92cf33e3).
  [Work Items](https://app.notion.com/p/35b6853b9c1b477eaa8fea44c904ad3f)
  (data source `collection://d4ee0676-be6c-49b2-9d02-90d0bf65bd8d`) filtered by Product, and
  [Epics](https://app.notion.com/p/537644df5f9c49ab96b9215e59f6c36f)
  (`collection://93cde8d5-7927-40b3-858f-3d8415cc0c36`).
- **Records:** Agency records (decision logs, retired registers, build logs) live in the private repository `lightlysaltedhq/docs` (local clone `~/Work/Docs`). This product has no folder there yet; create `salt-for-wordpress/` when it first needs a decision log, and never keep one in this repository.

### Finding the next piece of work

1. If you have been given a Work Item, do that one.
2. Otherwise read this Product's Work Items. Finish anything In Progress before starting something
   new.
3. Choose from Backlog. Skip Blocked items: each one waits on an owner decision, so raise it rather
   than building round it. Skip any item whose Depends on items are not all Done.
4. Work through this Product's open Epics in Rank order: Rank 1 is next. Take the lowest-ranked
   Epic that has an item you can start, and within it go by Depends on, then the item's Priority
   (Urgent, High, Medium, Low). An Epic's P1–P4 is a coarse band; Rank is the order. For a batch
   of work or a plan, run the `next-work-units` skill.
5. Work you find along the way becomes a new Work Item (this Product, the right Epic, Status
   Backlog), not a TODO comment or a note in a file.

### Recording progress

- Set the Work Item to In Progress when you start it.
- When it merges, set it to Done and add the PR, merge commit and checks to its body. Say
  separately whether it is deployed: merged and deployed are different facts.
- If only part of it is done, leave it In Progress and say in the body what remains.
