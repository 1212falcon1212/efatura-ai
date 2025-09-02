import { render, screen } from '@testing-library/react'
import DataTable, { type Column } from '../components/ui/DataTable'

type Row = { id: number; name: string }

test('renders rows and columns', () => {
  const columns: Array<Column<Row>> = [
    { key: 'id', label: 'ID' },
    { key: 'name', label: 'Ad' },
  ]
  const rows: Row[] = [
    { id: 1, name: 'A' },
    { id: 2, name: 'B' },
  ]
  render(<DataTable columns={columns} rows={rows} getRowId={(r) => r.id} />)
  expect(screen.getAllByRole('columnheader', { name: 'ID' }).length).toBeGreaterThan(0)
  expect(screen.getAllByRole('columnheader', { name: 'Ad' }).length).toBeGreaterThan(0)
  expect(screen.getByText('A')).toBeInTheDocument()
  expect(screen.getByText('B')).toBeInTheDocument()
})


